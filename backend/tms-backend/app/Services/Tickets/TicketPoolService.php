<?php

namespace App\Services\Tickets;

use App\Enums\TicketStatus;
use App\Exceptions\TicketAlreadyAssignedException;
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
     * @return Builder<Ticket>
     */
    public function poolQuery(): Builder
    {
        return Ticket::query()
            ->where('tenant_id', $this->tenantContext->requireTenant())
            ->where('status', TicketStatus::Pending->value)
            ->whereNull('agent_id');
    }

    /**
     * Atomically assign $ticketId to $agent. SELECT ... FOR UPDATE inside the
     * transaction blocks a second, concurrent pull attempt at the database
     * row-lock level until the first transaction commits or rolls back — the
     * second call then re-reads the now-committed row, sees agent_id is no
     * longer null, and fails with TicketAlreadyAssignedException instead of
     * both requests "succeeding" and silently double-assigning the ticket.
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

            $ticket->agent_id = $agent->id;
            $ticket->status = TicketStatus::InProgress;
            $ticket->save();

            $this->auditService->log('ticket.pulled', $ticket, newValues: ['agent_id' => $agent->id]);

            return $ticket;
        });
    }
}
