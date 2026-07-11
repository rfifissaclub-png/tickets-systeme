<?php

namespace App\Services\Tickets;

use App\Enums\TicketStatus;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Exceptions\TicketNotQualifiedException;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

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
     * @return Builder<Ticket>
     */
    public function poolQuery(User $agent): Builder
    {
        $query = Ticket::query()
            ->where('tenant_id', $this->tenantContext->requireTenant())
            ->where('status', TicketStatus::Pending->value)
            ->whereNull('agent_id');

        if ($this->isSkillGated($agent)) {
            // No rows configured means no rows visible — fails closed, same
            // as every other RLS/permission boundary in this app, rather
            // than defaulting a freshly-created agent to "sees everything".
            $query->whereIn('ticket_type_id', $this->qualifiedTicketTypeIds($agent));
        }

        return $query;
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
     */
    public function pull(string $ticketId, User $agent): Ticket
    {
        return DB::transaction(function () use ($ticketId, $agent) {
            $ticket = Ticket::query()
                ->where('tenant_id', $this->tenantContext->requireTenant())
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
