<?php

namespace App\Services\Tickets;

use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Exceptions\TicketNotQualifiedException;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pool → Agent chooses ticket → Database Lock → Assignment → My Desk.
 *
 * The pull() method is the one operation in this codebase where a race
 * genuinely matters: two agents can both load the pool listing, both see the
 * same unassigned ticket, and both hit "pull" within milliseconds of each
 * other. Whoever's transaction commits first must win; the other must get a
 * clean "already taken" error, never a silently overwritten assignment.
 */
class TicketPoolService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditService $auditService,
    ) {}

    /**
     * A front-line agent only ever sees pool tickets for the types they've
     * been declared qualified for (agent_skills — see the cahier des
     * charges' "Réservoir filtré par compétences"). Someone who can assign
     * tickets to *other* people (Manager/Tenant Admin) needs the full,
     * unfiltered pool for oversight instead — the same distinction
     * TicketSearchService draws with tickets.viewAny, just keyed off a
     * different permission because "can see everything" and "can hand work
     * to others" are different capabilities here.
     *
     * A Super Admin has no tenant of their own — rather than reject them
     * (they do hold tickets.assign, same as a Manager/Tenant Admin), an
     * unbound context sees the pool aggregated across every tenant instead
     * of one. Executed here, inside the (possible) bypass, rather than
     * returning a lazy Builder for the controller to paginate: the RLS
     * bypass this needs is only active for the duration of this call, not
     * however long the caller takes to eventually run the query.
     */
    public function poolQuery(User $agent, int $perPage): LengthAwarePaginator
    {
        $tenantId = $this->tenantContext->id();

        $build = function () use ($agent, $tenantId, $perPage) {
            $query = Ticket::query()
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('status', TicketStatus::Pending->value)
                ->whereNull('agent_id')
                ->with(['ticketType', 'client']);

            if ($this->isSkillGated($agent)) {
                // No rows configured means no rows visible — fails closed,
                // same as every other RLS/permission boundary in this app,
                // rather than defaulting a freshly-created agent to "sees
                // everything".
                $query->whereIn('ticket_type_id', $this->qualifiedTicketTypeIds($agent));
            }

            return $query->orderBy('created_at')->paginate($perPage);
        };

        return $this->tenantContext->runScoped('platform-wide ticket pool (super admin)', $build);
    }

    /**
     * Atomically assign $ticketId to $agent. SELECT ... FOR UPDATE inside the
     * transaction blocks a second, concurrent pull attempt at the database
     * row-lock level until the first transaction commits or rolls back — the
     * second call then re-reads the now-committed row, sees agent_id is no
     * longer null, and fails with TicketAlreadyAssignedException instead of
     * both requests "succeeding" and silently double-assigning the ticket.
     *
     * Skill eligibility is re-checked here too, not just in poolQuery()'s
     * listing: pull() takes a raw ticket id, so a skill-gated agent guessing
     * or replaying an id they saw before losing a qualification must still
     * be rejected, not just steered away by what the listing shows them.
     *
     * Same Super Admin handling as poolQuery(): an unbound context may pull
     * any tenant's pending ticket, not just one it happens to match.
     */
    public function pull(string $ticketId, User $agent): Ticket
    {
        $tenantId = $this->tenantContext->id();

        $run = fn () => $this->pullWithinTransaction($ticketId, $agent, $tenantId);

        return $this->tenantContext->runScoped('platform-wide ticket pull (super admin)', $run);
    }

    private function pullWithinTransaction(string $ticketId, User $agent, ?string $tenantId): Ticket
    {
        return DB::transaction(function () use ($ticketId, $agent, $tenantId) {
            $ticket = Ticket::query()
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->lockForUpdate()
                ->find($ticketId);

            if ($ticket === null) {
                throw (new ModelNotFoundException)->setModel(Ticket::class, [$ticketId]);
            }

            if ($ticket->agent_id !== null || $ticket->status !== TicketStatus::Pending) {
                throw new TicketAlreadyAssignedException($ticket);
            }

            if ($this->isSkillGated($agent) && ! in_array($ticket->ticket_type_id, $this->qualifiedTicketTypeIds($agent), true)) {
                throw new TicketNotQualifiedException($ticket);
            }

            $ticket->agent_id = $agent->id;
            $ticket->status = TicketStatus::InProgress;
            $ticket->save();

            $this->auditService->log('ticket.pulled', $ticket, newValues: ['agent_id' => $agent->id]);

            return $ticket;
        });
    }

    /**
     * Per-agent ticket workload for a tenant — the cahier des charges'
     * "bucket par agent": how many active (not yet Resolved/Closed) tickets
     * each assignable user already carries, and, when $ticketTypeId is
     * given, whether they hold the matching agent_skills qualification —
     * lets a Manager/Tenant Admin pick who to (re)assign a ticket to before
     * TicketService::assign() enforces the same qualification rule.
     *
     * A Super Admin has no tenant of their own, so $tenantIdFilter is
     * required for them (there's no single "current tenant" to default to,
     * and a workload listing spanning every tenant at once would mix
     * unrelated agents together for no actionable reason — unlike
     * UserManagementService::paginate(), nothing here is usefully served by
     * an unfiltered cross-tenant view).
     *
     * @return Collection<int, array{id: string, name: string, active_ticket_count: int, qualified: ?bool}>
     */
    public function agentWorkload(User $actor, ?string $tenantIdFilter, ?string $ticketTypeId): Collection
    {
        $isSuperAdmin = $actor->hasRole(RoleName::SuperAdmin->value);
        $tenantId = $isSuperAdmin ? $tenantIdFilter : $this->tenantContext->requireTenant();

        if ($tenantId === null) {
            throw ValidationException::withMessages([
                'tenant_id' => [__('messages.tickets.tenant_id_required_for_workload')],
            ]);
        }

        // withTeam(), even for a non-Super-Admin actor already bound to
        // this same tenant: roles()/qualifiedTicketTypes() are resolved
        // per-row below independently of whatever team happens to already
        // be bound, so binding it explicitly here keeps this method correct
        // regardless of caller — the same reasoning as every other cross-
        // tenant-capable method in this class.
        $build = fn () => $this->tenantContext->withTeam($tenantId, function () use ($tenantId, $ticketTypeId) {
            $agents = User::query()
                ->where('tenant_id', $tenantId)
                ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                    RoleName::SupportAgent->value,
                    RoleName::Manager->value,
                    RoleName::TenantAdmin->value,
                ]))
                ->withCount(['assignedTickets as active_ticket_count' => fn ($query) => $query->whereNotIn(
                    'status',
                    [TicketStatus::Resolved->value, TicketStatus::Closed->value],
                )])
                ->orderBy('name')
                ->get();

            return $agents->map(fn (User $agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'active_ticket_count' => $agent->active_ticket_count,
                'qualified' => $ticketTypeId === null ? null : (
                    ! $this->isSkillGated($agent)
                    || $agent->qualifiedTicketTypes()->where('ticket_types.id', $ticketTypeId)->exists()
                ),
            ]);
        });

        return $this->tenantContext->runScoped('agent workload listing (super admin)', $build);
    }

    private function isSkillGated(User $agent): bool
    {
        return ! $agent->can('tickets.assign');
    }

    /**
     * @return list<string>
     */
    private function qualifiedTicketTypeIds(User $agent): array
    {
        return $agent->qualifiedTicketTypes()->pluck('ticket_types.id')->all();
    }
}
