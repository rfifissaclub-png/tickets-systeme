<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

class TicketCommentPolicy
{
    public function viewAny(User $actor, Ticket $ticket): bool
    {
        return app(TicketPolicy::class)->view($actor, $ticket);
    }

    public function create(User $actor, Ticket $ticket): bool
    {
        if ($ticket->status === TicketStatus::Closed) {
            return false;
        }

        return $actor->id === $ticket->client_id
            || $actor->id === $ticket->agent_id
            || $actor->can('tickets.update');
    }

    /**
     * A stricter version of create(): even a ticket participant (the client
     * themselves) can never post an internal note — that requires the
     * tickets.viewInternal permission on top of ordinary participation.
     */
    public function createInternal(User $actor, Ticket $ticket): bool
    {
        return $this->create($actor, $ticket) && $actor->can('tickets.viewInternal');
    }
}
