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

class TicketService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TicketStatusTransitions $transitions,
        private readonly AuditService $auditService,
    ) {}

    /**
     * @param  array{ticket_type_id: string, client_id?: string, subject: string, description: string, priority?: string}  $data
     */
    public function create(User $actor, array $data): Ticket
    {
        // A plain Client can only ever raise a ticket for themselves — the
        // broader "create tickets on behalf of any client" ability requires
        // the tickets.create *permission*, which Clients are deliberately
        // not granted (see RoleAndPermissionSeeder).
        $clientId = $actor->hasRole(RoleName::Client->value) ? $actor->id : ($data['client_id'] ?? $actor->id);

        return Ticket::query()->create([
            'tenant_id' => $this->tenantContext->requireTenant(),
            'ticket_type_id' => $data['ticket_type_id'],
            'client_id' => $clientId,
            'subject' => $data['subject'],
            'description' => $data['description'],
            'priority' => $data['priority'] ?? 'medium',
            'status' => TicketStatus::Pending,
        ]);
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
