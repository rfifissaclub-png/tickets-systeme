<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tenant isolation is enforced at the database engine level via PostgreSQL
     * Row-Level Security, not just in application query scopes. Two session
     * settings drive every policy below, both set per-request by
     * App\Http\Middleware\ResolveTenantContext / App\Services\Tenancy\TenantContext:
     *
     *   - app.tenant_id    the authenticated user's tenant (NULL for Super Admins
     *                      and for requests before tenant resolution)
     *   - app.bypass_rls   'on' only inside an explicitly authorized, audited
     *                      TenantContext::bypass() call (Super Admin cross-tenant
     *                      operations). Never set implicitly.
     *
     * Because Postgres compares NULL = NULL as unknown (not true), a session
     * with no tenant_id set and no bypass sees zero rows on every RLS-protected
     * table — the isolation fails closed by default.
     *
     * FORCE ROW LEVEL SECURITY is required on every table below: by default
     * Postgres RLS does not apply to the table's owning role, and the Laravel
     * app connects as the same role that ran these migrations (and therefore
     * owns the tables). Without FORCE, RLS would silently do nothing.
     */
    public function up(): void
    {
        $tenantScoped = [
            ['table' => 'tenants', 'tenant_column' => 'id'],
            ['table' => 'users', 'tenant_column' => 'tenant_id'],
        ];

        foreach ($tenantScoped as $spec) {
            $table = $spec['table'];
            $column = $spec['tenant_column'];

            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");

            DB::statement("
                CREATE POLICY {$table}_tenant_isolation ON {$table}
                FOR SELECT USING (
                    {$column} = current_setting('app.tenant_id', true)::uuid
                    OR current_setting('app.bypass_rls', true) = 'on'
                )
            ");

            DB::statement("
                CREATE POLICY {$table}_tenant_write ON {$table}
                FOR UPDATE USING (
                    {$column} = current_setting('app.tenant_id', true)::uuid
                    OR current_setting('app.bypass_rls', true) = 'on'
                ) WITH CHECK (
                    {$column} = current_setting('app.tenant_id', true)::uuid
                    OR current_setting('app.bypass_rls', true) = 'on'
                )
            ");

            DB::statement("
                CREATE POLICY {$table}_tenant_delete ON {$table}
                FOR DELETE USING (
                    {$column} = current_setting('app.tenant_id', true)::uuid
                    OR current_setting('app.bypass_rls', true) = 'on'
                )
            ");

            // INSERT is bypass-only: provisioning a new tenant, or creating a
            // user outside the caller's own tenant context (including
            // tenant_id IS NULL Super Admin users), requires an authorized,
            // audited TenantContext::bypass() call. Creating a user inside
            // one's own tenant is covered because {$column} = current tenant
            // is satisfiable without bypass.
            DB::statement("
                CREATE POLICY {$table}_tenant_insert ON {$table}
                FOR INSERT WITH CHECK (
                    {$column} = current_setting('app.tenant_id', true)::uuid
                    OR current_setting('app.bypass_rls', true) = 'on'
                )
            ");
        }

        // audit_logs: readable only within the current tenant (or via bypass).
        // INSERT is intentionally unconditional (WITH CHECK (true)): audit
        // rows are only ever written by AuditService/AuditableObserver, never
        // from user-controlled queries, and the tenant context is frequently
        // *not yet bound* at the moment a row must be written — e.g. a login
        // audit entry is written from the Login event, which fires before
        // ResolveTenantContext has anything to bind (login is unauthenticated
        // by definition). Gating INSERT by tenant match would make that
        // legitimate write impossible. The isolation boundary that matters —
        // one tenant reading another's audit trail — is enforced by SELECT.
        // Deliberately no UPDATE/DELETE policy at all: once RLS is enabled and
        // forced, the absence of a policy for a command denies it outright,
        // making the audit trail immutable at the database engine level for
        // every role except a true Postgres superuser.
        DB::statement('ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE audit_logs FORCE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY audit_logs_tenant_isolation ON audit_logs
            FOR SELECT USING (
                tenant_id = current_setting('app.tenant_id', true)::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");

        DB::statement('
            CREATE POLICY audit_logs_insert ON audit_logs
            FOR INSERT WITH CHECK (true)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['tenants', 'users'] as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_write ON {$table}");
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_delete ON {$table}");
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_insert ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }

        DB::statement('DROP POLICY IF EXISTS audit_logs_tenant_isolation ON audit_logs');
        DB::statement('DROP POLICY IF EXISTS audit_logs_insert ON audit_logs');
        DB::statement('ALTER TABLE audit_logs NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE audit_logs DISABLE ROW LEVEL SECURITY');
    }
};
