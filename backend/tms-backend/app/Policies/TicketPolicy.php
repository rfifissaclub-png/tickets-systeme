<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

/**
 * Two overlapping but distinct rules govern access, on top of the
 * users.viewAny-style permission catalog seeded in RoleAndPermissionSeeder:
 *
 *   - Ownership: the client who raised a ticket, or the agent it's assigned
 *     to, can always view it — and an assigned agent can always update it —
 *     regardless of whether they hold the broader tickets.* permission.
 *   - Read-only lifecycle: Resolved tickets block content edits (update())
 *     but not status transitions (transition()); Closed tickets block both.
 *     The enforce_ticket_immutability DB trigger enforces the same rule as a
 *     backstop, so this policy denying access is a clean 403/409, not the
 *     only thing standing between a bug and a corrupted closed ticket.
 */
class TicketPolicy
{
    /**
     * Everyone tenant-bound may hit the list endpoint — a Client needs to see
     * "my tickets" too. tickets.viewAny only controls the *scope* of results
     * (all tenant tickets vs. just the caller's own), applied by
     * TicketController::index(), not access to the endpoint itself.
     */
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Ticket $ticket): bool
    {
        if ($actor->id === $ticket->client_id || $actor->id === $ticket->agent_id) {
            return true;
        }

        return $actor->can('tickets.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('tickets.create');
    }

    public function update(User $actor, Ticket $ticket): bool
    {
        if ($ticket->isReadOnly()) {
            return false;
        }

        if ($actor->id === $ticket->agent_id) {
            return true;
        }

        return $actor->can('tickets.update');
    }

    public function delete(User $actor, Ticket $ticket): bool
    {
        return $actor->can('tickets.delete');
    }

    public function transition(User $actor, Ticket $ticket): bool
    {
        if ($ticket->status === TicketStatus::Closed) {
            return false;
        }

        if ($actor->id === $ticket->agent_id) {
            return true;
        }

        return $actor->can('tickets.update');
    }

    public function assign(User $actor, Ticket $ticket): bool
    {
        return ! $ticket->isReadOnly() && $actor->can('tickets.assign');
    }

    public function pull(User $actor, Ticket $ticket): bool
    {
        return $ticket->isInPool() && $actor->can('tickets.pull');
    }

    public function viewInternalComments(User $actor): bool
    {
        return $actor->can('tickets.viewInternal');
    }
}
