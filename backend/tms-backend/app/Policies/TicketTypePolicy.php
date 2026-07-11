<?php

namespace App\Policies;

use App\Models\TicketType;
use App\Models\User;

/**
 * Visibility isn't a security boundary here — every tenant member needs to
 * see the available types to file a ticket at all, and RLS already confines
 * the list to their own tenant. Only mutation is permission-gated.
 */
class TicketTypePolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, TicketType $ticketType): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return $actor->can('ticket_types.manage');
    }

    public function update(User $actor, TicketType $ticketType): bool
    {
        return $actor->can('ticket_types.manage');
    }

    public function delete(User $actor, TicketType $ticketType): bool
    {
        return $actor->can('ticket_types.manage');
    }
}
