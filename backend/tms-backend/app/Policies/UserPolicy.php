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

    /**
     * Separate from update(): role assignment is a privilege-escalation
     * surface (grant yourself Tenant Admin, etc.), so it needs its own,
     * narrower permission rather than piggybacking on the general
     * "can edit this user's name/email" check.
     */
    public function manageRoles(User $actor, User $target): bool
    {
        return $actor->id !== $target->id && $actor->can('users.manageRoles');
    }

    /**
     * Assigning ticket-type skills to an agent — a staffing/workflow
     * decision (who's qualified to pull what), not a security boundary the
     * way role assignment is, so it's gated by a lighter permission that
     * Managers hold too, not just Tenant Admin.
     */
    public function manageSkills(User $actor, User $target): bool
    {
        return $actor->can('agent_skills.manage');
    }
}
