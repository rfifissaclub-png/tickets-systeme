<?php

namespace App\Services\Tickets;

use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransitionException;
use App\Exceptions\TicketIsReadOnlyException;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketStatusChangedNotification;
use App\Services\Audit\AuditService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TicketStatusTransitions $transitions,
        private readonly AuditService $auditService,
    ) {}

    /**
     * @param  array{ticket_type_id: string, tenant_id?: string, client_id?: string, subject: string, description: string, priority?: string}  $data
     */
    public function create(User $actor, array $data): Ticket
    {
        // A Super Admin has no tenant of their own to fall back to — unlike
        // every other actor, StoreTicketRequest requires them to name one
        // explicitly (see its own docblock on why there's no `exists:`
        // check backing it).
        $isSuperAdmin = $actor->hasRole(RoleName::SuperAdmin->value);
        $targetTenantId = $isSuperAdmin ? $data['tenant_id'] : $this->tenantContext->requireTenant();

        // A plain Client can only ever raise a ticket for themselves — the
        // broader "create tickets on behalf of any client" ability requires
        // the tickets.create *permission*, which Clients are deliberately
        // not granted (see RoleAndPermissionSeeder). A Super Admin has no
        // client identity of their own either, so StoreTicketRequest
        // requires them to name one explicitly too.
        $clientId = $actor->hasRole(RoleName::Client->value) ? $actor->id : ($data['client_id'] ?? $actor->id);

        $create = function () use ($isSuperAdmin, $targetTenantId, $clientId, $data) {
            // Nothing else stops a Super Admin naming a tenant and a client
            // that don't actually belong together — StoreTicketRequest's
            // own `exists:users,id` check is skipped for them (RLS would
            // block it regardless of correctness), so this is the one
            // place left to catch the mismatch, now safely inside the
            // bypass this lookup itself needs.
            if ($isSuperAdmin && ! User::query()->where('id', $clientId)->where('tenant_id', $targetTenantId)->exists()) {
                throw ValidationException::withMessages([
                    'client_id' => [__('messages.tickets.client_tenant_mismatch')],
                ]);
            }

            return Ticket::query()->create([
                'tenant_id' => $targetTenantId,
                'ticket_type_id' => $data['ticket_type_id'],
                'client_id' => $clientId,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'priority' => $data['priority'] ?? 'medium',
                'status' => TicketStatus::Pending,
            ]);
        };

        return $isSuperAdmin ? $this->tenantContext->bypass('ticket creation (super admin)', $create) : $create();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, array $data): Ticket
    {
        // TicketPolicy::update() already blocks this at the HTTP layer; this
        // is the same defense-in-depth belt-and-suspenders as everywhere
        // else — a direct service call that skips the policy still can't
        // touch a read-only ticket, and the DB trigger blocks it a third way.
        if ($ticket->isReadOnly()) {
            throw new TicketIsReadOnlyException($ticket->status);
        }

        $ticket->fill($data)->save();

        return $ticket;
    }

    public function transitionStatus(Ticket $ticket, TicketStatus $to): Ticket
    {
        $this->transitions->assertAllowed($ticket->status, $to);
        $previousStatus = $ticket->status;

        return DB::transaction(function () use ($ticket, $to, $previousStatus) {
            $ticket->status = $to;

            if ($to === TicketStatus::Resolved) {
                $ticket->resolved_at = now();
            }

            if ($to === TicketStatus::Closed) {
                $ticket->closed_at = now();
            }

            if ($to === TicketStatus::InProgress && $ticket->resolved_at !== null && $ticket->getOriginal('status') === TicketStatus::Resolved) {
                // Reopened from Resolved: clear the resolved timestamp, it no
                // longer reflects reality.
                $ticket->resolved_at = null;
            }

            $ticket->save();

            $this->auditService->log('ticket.status_changed', $ticket, newValues: ['status' => $to->value]);

            $this->notifyStatusChange($ticket, $previousStatus);

            return $ticket;
        });
    }

    /**
     * Direct assignment by a Manager/Tenant Admin (as opposed to an agent
     * pulling from the pool themselves — see TicketPoolService::pull()).
     * Blocked on Resolved/Closed tickets both here and, as a backstop, by
     * the enforce_ticket_immutability DB trigger.
     */
    public function assign(Ticket $ticket, User $agent): Ticket
    {
        if ($ticket->isReadOnly()) {
            throw new TicketIsReadOnlyException($ticket->status);
        }

        // Belt-and-suspenders alongside AssignTicketRequest's own checks:
        // those are RLS-scoped to whichever tenant validated the request,
        // so they already guarantee both of these for everyone but a Super
        // Admin — for them specifically, both checks are skipped there (RLS
        // would block the lookup regardless of correctness) and enforced
        // here instead, the one place that runs after the cross-tenant
        // bypass TicketAssignmentController wraps this in.
        //
        // withTeam($agent->tenant_id, ...): hasAnyRole()/can() are
        // themselves team-scoped (Spatie's "teams" feature), independent of
        // the RLS bypass above — without binding the *agent's own* team
        // here, this would check against whichever team the acting request
        // happens to be bound to (the Super Admin's own platform team, not
        // the agent's), finding no matching role row for a genuinely valid
        // agent and rejecting them incorrectly.
        [$isAssignable, $isSkillGated] = $this->tenantContext->withTeam(
            $agent->tenant_id,
            fn () => [
                $agent->hasAnyRole([RoleName::SupportAgent->value, RoleName::Manager->value, RoleName::TenantAdmin->value]),
                ! $agent->can('tickets.assign'),
            ],
        );

        if (! $isAssignable) {
            throw ValidationException::withMessages([
                'agent_id' => [__('messages.tickets.agent_not_assignable')],
            ]);
        }

        if ($agent->tenant_id !== $ticket->tenant_id) {
            throw ValidationException::withMessages([
                'agent_id' => [__('messages.tickets.agent_tenant_mismatch')],
            ]);
        }

        // Same "Réservoir filtré par compétences" rule TicketPoolService
        // enforces for self-service pulling (see its own isSkillGated()) —
        // a Manager/Tenant Admin manually handing a ticket to someone else
        // must respect the same skill boundary, or agent_skills becomes
        // meaningless the moment anyone with tickets.assign starts
        // reassigning by hand instead of letting agents pull for
        // themselves. A target who can assign tickets themselves
        // (Manager/Tenant Admin) is exempt, same exemption isSkillGated()
        // grants — supervisory roles aren't tracked per-type the way
        // front-line agents are.
        if ($isSkillGated && ! $agent->qualifiedTicketTypes()->where('ticket_types.id', $ticket->ticket_type_id)->exists()) {
            throw ValidationException::withMessages([
                'agent_id' => [__('messages.tickets.agent_not_qualified')],
            ]);
        }

        return DB::transaction(function () use ($ticket, $agent) {
            $ticket->agent_id = $agent->id;

            if ($ticket->status === TicketStatus::Pending) {
                $ticket->status = TicketStatus::InProgress;
            }

            $ticket->save();

            $this->auditService->log('ticket.assigned', $ticket, newValues: ['agent_id' => $agent->id]);

            if ($agent->id !== Auth::id()) {
                $agent->notify(new TicketAssignedNotification($ticket));
            }

            return $ticket;
        });
    }

    /**
     * Send a ticket back to the pool: clear its agent and return it to
     * Pending. Deliberately not routed through TicketStatusTransitions —
     * Pending is never a valid *target* in that graph (nothing normally
     * transitions back to it), because this is a distinct operation
     * ("un-claim", not a lifecycle move) only valid from the two
     * actively-assigned states.
     */
    public function unassign(Ticket $ticket): Ticket
    {
        if (! in_array($ticket->status, [TicketStatus::InProgress, TicketStatus::OnHold], true)) {
            throw new InvalidTicketTransitionException($ticket->status, TicketStatus::Pending);
        }

        return DB::transaction(function () use ($ticket) {
            $ticket->agent_id = null;
            $ticket->status = TicketStatus::Pending;
            $ticket->save();

            $this->auditService->log('ticket.unassigned', $ticket);

            return $ticket;
        });
    }

    /**
     * Notifies the client and (if assigned) the agent — but never whoever
     * just performed the transition themselves, no one needs telling about
     * their own action.
     */
    private function notifyStatusChange(Ticket $ticket, TicketStatus $previousStatus): void
    {
        $actorId = Auth::id();

        foreach (array_filter([$ticket->client, $ticket->agent]) as $recipient) {
            if ($recipient->id !== $actorId) {
                $recipient->notify(new TicketStatusChangedNotification($ticket, $previousStatus));
            }
        }
    }
}
