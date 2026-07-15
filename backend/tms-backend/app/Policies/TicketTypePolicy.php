<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\TicketType;
use App\Models\User;

/**
 * Ticket types are a single platform-wide catalog (see TicketType's own
 * docblock), not a per-tenant resource — every tenant member needs to see
 * the available types to file a ticket at all, so viewing is never
 * restricted. Mutation is checked directly against the Super Admin role,
 * not a grantable permission — same reasoning as TenantPolicy's
 * platform-wide operations: a "ticket_types.manage"-style permission could
 * in principle be granted to a Tenant Admin later, which would let one
 * tenant's admin rename or delete a category every other tenant also files
 * tickets under. That must never be possible via a permission grant.
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
        return $actor->hasRole(RoleName::SuperAdmin->value);
    }

    public function update(User $actor, TicketType $ticketType): bool
    {
        return $actor->hasRole(RoleName::SuperAdmin->value);
    }

    public function delete(User $actor, TicketType $ticketType): bool
    {
        return $actor->hasRole(RoleName::SuperAdmin->value);
    }
}
