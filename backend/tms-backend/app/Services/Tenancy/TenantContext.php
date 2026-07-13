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
    /**
     * Spatie's "teams" feature makes `team_foreign_key` part of the
     * composite PRIMARY KEY on `model_has_roles`/`model_has_permissions`
     * (see the create_permission_tables migration) — Postgres forbids NULL
     * in a primary key column, so a *role assignment* can never actually be
     * team-less the way a role *definition* can (`roles.tenant_id` stays
     * genuinely nullable — that column isn't part of any primary key).
     * Super Admins have no real tenant, so their role assignments use this
     * fixed, reserved id as a stand-in "platform team" instead of null.
     * Never written to any RLS-protected table's `tenant_id` column, never
     * a real Tenant row — purely an internal Spatie bookkeeping value. Only
     * ever surfaced by actually running the seeder against real Postgres
     * (SQLSTATE 23502 not-null violation), not visible from schema review.
     */
    public const string PLATFORM_TEAM_ID = '00000000-0000-0000-0000-000000000000';

    private ?string $tenantId = null;

    private ?string $userId = null;

    private bool $bypassed = false;

    public function set(string $tenantId): void
    {
        // Postgres's SET command does not accept a bound parameter (`SET x
        // = $1` is a syntax error — SET expects a literal) — set_config()
        // is the parameterized equivalent, a regular function call rather
        // than special SET-statement syntax. is_local=false matches SET's
        // session-level (not transaction-local) scope, per this class's own
        // docblock. Only ever surfaced by actually running this against
        // real Postgres, not visible from reading the SQL.
        DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenantId]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        $this->tenantId = $tenantId;
    }

    /**
     * Binds only Spatie's team resolver to the reserved platform team —
     * deliberately does NOT touch `app.tenant_id`/RLS state (unlike set()):
     * a Super Admin must stay RLS-unbound by default (fails closed on every
     * tenant-owned table without an explicit bypass), this only affects
     * where their own role/permission rows are looked up.
     */
    public function bindPlatformTeam(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(self::PLATFORM_TEAM_ID);
    }

    /**
     * Spatie team id for a given user's tenant — the reserved platform team
     * for a tenant-less (Super Admin) user, their real tenant id otherwise.
     * Centralizes the null->sentinel substitution so every call site that
     * sets Spatie's team resolver for an arbitrary target user agrees on it.
     */
    public static function teamIdFor(?string $tenantId): string
    {
        return $tenantId ?? self::PLATFORM_TEAM_ID;
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
        // See set()'s comment — SET does not accept a bound parameter.
        DB::statement("SELECT set_config('app.user_id', ?, false)", [$userId]);

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
     *   - Pre-authentication lookups in the login flow: resolving a
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
     * Narrow, *unaudited* RLS bypass used only to let Sanctum's own guard
     * resolve the `users` row behind an incoming bearer token — see
     * App\Http\Middleware\PrimeSanctumAuthentication, which runs this
     * before `auth:sanctum` on every request to a protected route.
     *
     * This is a genuine chicken-and-egg gap in the pipeline: `auth:sanctum`
     * must run before `tenant.context` (you have to know *who* is asking
     * before you know *which tenant* they belong to), but the `users` table
     * is itself RLS-protected, so without this, Sanctum's own lookup of the
     * token's owning user finds zero rows on a fresh connection where no
     * tenant is bound yet — meaning no bearer token could ever authenticate
     * at all. Deliberately not the audited bypass() above: this runs on
     * every request to a protected route whether or not it actually carries
     * a valid token, and isn't itself an access to another tenant's data —
     * it's how the framework establishes identity in the first place. The
     * moment identity *is* established, ResolveTenantContext binds the real
     * tenant (or the platform team, for a Super Admin) and every further
     * query in the request is fully RLS-governed again. Only ever surfaced
     * by serving a real HTTP request against real Postgres and using a
     * bearer token on a second request — every other verification in this
     * codebase (migrations, Pest under RefreshDatabase) masked it, because
     * a shared, long-lived test connection already had a tenant bound from
     * earlier direct TenantContext::set() calls in the same test.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function primeAuthentication(callable $callback): mixed
    {
        DB::statement("SET app.bypass_rls = 'on'");

        try {
            return $callback();
        } finally {
            DB::statement("SET app.bypass_rls = 'off'");
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
