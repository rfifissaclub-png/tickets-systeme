<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleName::SuperAdmin->value);
    }

    public function view(User $actor, Client $client): bool
    {
        // Only allow viewing a client when the actor belongs to the same
        // tenant. Super Admin is handled by Gate::before in AppServiceProvider.
        return $actor->tenant_id !== null && $actor->tenant_id === $client->tenant_id;
    }

    public function create(User $actor): bool
    {
        return $actor->can('tenants.update');
    }

    public function update(User $actor, Client $client): bool
    {
        // Must be in the same tenant and have the tenants.update permission.
        return $actor->tenant_id !== null && $actor->tenant_id === $client->tenant_id && $actor->can('tenants.update');
    }

    public function delete(User $actor, Client $client): bool
    {
        return $actor->hasRole(RoleName::SuperAdmin->value);
    }
}
