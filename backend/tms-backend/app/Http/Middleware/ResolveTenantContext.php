<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's tenant into TenantContext (and therefore
 * into the Postgres session RLS variable + Spatie's team resolver) before
 * any tenant-owned data is touched. Must run after auth:sanctum.
 *
 * Super Admins (tenant_id IS NULL) are intentionally left RLS-unbound here: a
 * request with no tenant context sees zero rows on every RLS-protected
 * table (fail closed). Super Admin routes that need cross-tenant access must
 * go through TenantContext::bypass() explicitly, scoped to that operation.
 * Spatie's team resolver still needs *something* bound for them though (see
 * TenantContext::PLATFORM_TEAM_ID) — otherwise their own role/permission
 * lookups (hasRole('Super Admin'), which the Gate::before hook in
 * AppServiceProvider depends on for literally everything) would silently
 * fail to match their own role assignment.
 */
class ResolveTenantContext
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $this->tenantContext->bindUser($user->id);

            if ($user->tenant_id !== null) {
                $this->tenantContext->set($user->tenant_id);
            } else {
                $this->tenantContext->bindPlatformTeam();
            }
        }

        return $next($request);
    }
}
