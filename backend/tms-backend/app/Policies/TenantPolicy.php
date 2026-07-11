<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Tenant;
use App\Models\User;

/**
 * Platform-wide operations (listing every tenant, provisioning a brand new
 * one, suspending one) are checked directly against the Super Admin role,
 * not a grantable permission — same reasoning as
 * ReportController::byTenant(): a "tenants.manage"-style permission could in
 * principle be granted to a Tenant Admin role later, which would let a
 * Tenant Admin see or create *other* tenants. That must never be possible
 * via a permission grant.
 *
 * Viewing/updating a single tenant stays permission-based (tenants.view/
 * tenants.update) because PostgreSQL RLS already confines a Tenant Admin's
 * session to their own tenant row regardless of what the permission grants —
 * there's no cross-tenant exposure to guard against there.
 */
class TenantPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleName::SuperAdmin->value);
    }

    public function view(User $actor, Tenant $tenant): bool
    {
        return $actor->can('tenants.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleName::SuperAdmin->value);
    }

    public function update(User $actor, Tenant $tenant): bool
    {
        return $actor->can('tenants.update');
    }

    public function delete(User $actor, Tenant $tenant): bool
    {
        return $actor->hasRole(RoleName::SuperAdmin->value);
    }
}
