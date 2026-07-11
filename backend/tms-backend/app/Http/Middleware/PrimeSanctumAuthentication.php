<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Must run immediately before `auth:sanctum` on every route that uses it.
 *
 * Forces Sanctum's guard to resolve (and cache) the bearer token's owning
 * user now, inside TenantContext::primeAuthentication()'s bypass — see that
 * method's docblock for why this is necessary at all: `auth:sanctum` runs
 * before `tenant.context` by necessity, but the `users` table is
 * RLS-protected, so without this, Sanctum's own lookup would find zero rows
 * on a fresh, not-yet-tenant-bound connection and no bearer token could ever
 * authenticate. Laravel's guards memoize the resolved user per request, so
 * the real `auth:sanctum` middleware that runs right after this one reuses
 * this result instead of re-querying.
 *
 * The forgetGuards() call below is unrelated to that RLS problem and just
 * as load-bearing: Illuminate\Auth\RequestGuard caches its resolved user
 * forever once resolved, and AuthManager caches the guard instance itself
 * per name — neither is ever reset by the framework. In a real deployment
 * each HTTP request is a fresh PHP process, so this is invisible there, but
 * Laravel's own test HTTP client reuses the same booted application (and
 * therefore the same AuthManager) across every simulated request in one
 * test — so without forgetting guards first, a second authenticated
 * request in the same test (e.g. one made after revoking the first
 * request's token) would silently reuse the first request's cached guard,
 * bound to the first request's token, and never re-authenticate at all.
 * Only ever surfaced by a test that revokes a token and then re-uses it in
 * the same test expecting a 401 — a single-request test can't see this.
 */
class PrimeSanctumAuthentication
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        Auth::forgetGuards();

        $this->tenantContext->primeAuthentication(function () {
            Auth::guard('sanctum')->user();
        });

        return $next($request);
    }
}
