<?php

namespace App\Policies;

use App\Models\User;

/**
 * Backed by the permission catalog seeded in RoleAndPermissionSeeder
 * (users.viewAny/view/create/update/delete), not hardcoded role names — a
 * Tenant Admin can grant "users.view" to any role without a code change.
 * Super Admin bypasses all of this via the Gate::before hook in
 * AppServiceProvider.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.viewAny');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $actor->can('users.update');
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->id !== $target->id && $actor->can('users.delete');
    }
}
