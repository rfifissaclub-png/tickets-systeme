<?php

namespace App\Services\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

/**
 * Every pre-authentication lookup (login, forgot/reset password) needs to
 * find a tenant by slug and/or a user by (tenant_id, email) before any
 * tenant context exists to satisfy RLS — see TenantContext::bypass() for why
 * that's an expected, audited use of the bypass, not a workaround.
 */
class TenantAwareUserResolver
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * Resolve a tenant slug to its id. Must be called from inside an
     * already-open TenantContext::bypass() block — it does not open its own,
     * so callers that need this alongside other bypassed work (e.g. the
     * password broker's own internal queries) only pay for one bypass.
     *
     * Returns null for "no tenant" (Super Admin), or false if the slug does
     * not resolve to any tenant.
     */
    public function resolveTenantId(?string $tenantSlug): string|false|null
    {
        if ($tenantSlug === null || $tenantSlug === '') {
            return null;
        }

        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();

        return $tenant?->id ?? false;
    }

    /**
     * Find the user for a login attempt. Self-contained: opens its own
     * bypass, since login has no other bypassed work around it.
     */
    public function findUser(?string $tenantSlug, string $email): ?User
    {
        return $this->tenantContext->bypass('pre-authentication credential lookup', function () use ($tenantSlug, $email) {
            $tenantId = $this->resolveTenantId($tenantSlug);

            if ($tenantId === false) {
                // Deliberately indistinguishable from "no such user": don't
                // let an unknown tenant slug become an oracle for tenant
                // enumeration via the login endpoint's error response.
                return null;
            }

            return User::query()->where('tenant_id', $tenantId)->where('email', $email)->first();
        });
    }
}
