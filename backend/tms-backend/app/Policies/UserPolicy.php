<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

class UserPolicy
{
    private const MANAGES_USERS = [
        RoleName::SuperAdmin->value,
        RoleName::TenantAdmin->value,
        RoleName::Manager->value,
    ];

    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole(self::MANAGES_USERS);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $actor->hasAnyRole(self::MANAGES_USERS);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $actor->hasAnyRole(self::MANAGES_USERS);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->id !== $target->id && $actor->hasAnyRole(self::MANAGES_USERS);
    }
}
