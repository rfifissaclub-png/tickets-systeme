<?php

namespace App\Services\Tenancy;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Single source of truth for "which tenant is this request operating as".
 *
 * Setting the tenant here does two things in lockstep:
 *   1. Sets the `app.tenant_id` Postgres session variable that every RLS
 *      policy checks (see the enable_row_level_security migration).
 *   2. Sets Spatie's PermissionsTeamResolver, so role/permission checks are
 *      scoped to the same tenant.
 *
 * `SET` (not `SET LOCAL`) is used deliberately: Laravel's default PHP-FPM
 * deployment opens a fresh PDO connection per request, so a session-level
 * setting cannot leak between requests. If this app ever moves to Octane or
 * persistent/pooled connections, this must change to reset the setting at
 * the start of every request (or move to SET LOCAL inside an explicit
 * per-request transaction) — a session-scoped SET would otherwise leak the
 * previous request's tenant onto the next one sharing that connection.
 */
class TenantContext
{
    private ?string $tenantId = null;

    private ?string $userId = null;

    private bool $bypassed = false;

    public function set(string $tenantId): void
    {
        DB::statement('SET app.tenant_id = ?', [$tenantId]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        $this->tenantId = $tenantId;
    }

    /**
     * Independent of tenant binding: notifications are RLS-scoped to
     * `notifiable_id = app.user_id`, not just tenant_id, so one user's
     * notifications are never visible to another in the same tenant (a
     * Manager and a Support Agent don't share an inbox). Bound for every
     * authenticated request, including Super Admins, who have no tenant.
     */
    public function bindUser(string $userId): void
    {
        DB::statement('SET app.user_id = ?', [$userId]);

        $this->userId = $userId;
    }

    public function id(): ?string
    {
        return $this->tenantId;
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    public function bound(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Run $callback with PostgreSQL RLS bypassed for the current session, for
     * narrowly-scoped, trusted system operations that must legitimately see
     * across tenants (or before any tenant is known at all). Two families of
     * caller, both required to justify the bypass with $reason:
     *
     *   - Authorized Super Admin cross-tenant operations. Callers are
     *     responsible for checking that authorization *before* calling this.
     *   - Pre-authentication lookups in the login flow (Phase 2): resolving a
     *     tenant by slug, then a user by email within it, necessarily happens
     *     before ResolveTenantContext has anything to bind — there is no
     *     tenant context yet by definition. The authorization boundary for
     *     that path is the password check immediately afterwards, not RLS.
     *
     * Every bypass is written to the audit trail with $reason, before the
     * bypass flag is even lifted, so cross-tenant/pre-auth access is always
     * traceable.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function bypass(string $reason, callable $callback): mixed
    {
        if ($this->bypassed) {
            // Already inside a bypass block — just run the callback rather
            // than nesting, which would make the reset below drop the outer
            // block's bypass prematurely.
            return $callback();
        }

        DB::statement("SET app.bypass_rls = 'on'");
        $this->bypassed = true;

        try {
            $result = $callback();

            AuditLog::query()->create([
                'tenant_id' => $this->tenantId,
                'actor_id' => auth()->id(),
                'action' => 'rls.bypass',
                'new_values' => ['reason' => $reason],
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'created_at' => now(),
            ]);

            return $result;
        } finally {
            DB::statement("SET app.bypass_rls = 'off'");
            $this->bypassed = false;
        }
    }

    /**
     * Guard for code paths that must never run without a resolved tenant
     * (e.g. creating tenant-owned records outside a bypass block).
     */
    public function requireTenant(): string
    {
        if ($this->tenantId === null) {
            throw new RuntimeException('No tenant is bound to the current request context.');
        }

        return $this->tenantId;
    }
}
