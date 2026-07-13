<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ticket_attachments: same tenant-only pattern as every other
        // tenant-owned table (see enable_row_level_security and
        // enable_row_level_security_for_tickets).
        DB::statement('ALTER TABLE ticket_attachments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ticket_attachments FORCE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY ticket_attachments_tenant_isolation ON ticket_attachments
            FOR SELECT USING (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");

        DB::statement("
            CREATE POLICY ticket_attachments_tenant_write ON ticket_attachments
            FOR UPDATE USING (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            ) WITH CHECK (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");

        DB::statement("
            CREATE POLICY ticket_attachments_tenant_delete ON ticket_attachments
            FOR DELETE USING (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");

        DB::statement("
            CREATE POLICY ticket_attachments_tenant_insert ON ticket_attachments
            FOR INSERT WITH CHECK (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");

        // notifications: scoped to BOTH the tenant and the specific
        // recipient (app.user_id) — this is a personal inbox, not shared
        // tenant data like everything else. A Manager and a Support Agent
        // in the same tenant must never see each other's notifications
        // through anything short of an explicit, audited bypass.
        DB::statement('ALTER TABLE notifications ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE notifications FORCE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY notifications_owner_isolation ON notifications
            FOR SELECT USING (
                (
                    tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                    AND notifiable_id = NULLIF(current_setting('app.user_id', true), '')::uuid
                )
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");

        DB::statement("
            CREATE POLICY notifications_owner_write ON notifications
            FOR UPDATE USING (
                (
                    tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                    AND notifiable_id = NULLIF(current_setting('app.user_id', true), '')::uuid
                )
                OR current_setting('app.bypass_rls', true) = 'on'
            ) WITH CHECK (
                (
                    tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                    AND notifiable_id = NULLIF(current_setting('app.user_id', true), '')::uuid
                )
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");

        // INSERT is not owner-scoped: the system creates a notification FOR
        // a recipient who isn't the current actor (e.g. an agent's action
        // notifies the client) — the recipient can't "check" as themselves
        // for a row about to be created on their behalf. Writes are trusted
        // application code only (notification classes + TenantDatabaseChannel),
        // same reasoning as audit_logs_insert.
        DB::statement("
            CREATE POLICY notifications_insert ON notifications
            FOR INSERT WITH CHECK (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS ticket_attachments_tenant_isolation ON ticket_attachments');
        DB::statement('DROP POLICY IF EXISTS ticket_attachments_tenant_write ON ticket_attachments');
        DB::statement('DROP POLICY IF EXISTS ticket_attachments_tenant_delete ON ticket_attachments');
        DB::statement('DROP POLICY IF EXISTS ticket_attachments_tenant_insert ON ticket_attachments');
        DB::statement('ALTER TABLE ticket_attachments NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ticket_attachments DISABLE ROW LEVEL SECURITY');

        DB::statement('DROP POLICY IF EXISTS notifications_owner_isolation ON notifications');
        DB::statement('DROP POLICY IF EXISTS notifications_owner_write ON notifications');
        DB::statement('DROP POLICY IF EXISTS notifications_insert ON notifications');
        DB::statement('ALTER TABLE notifications NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE notifications DISABLE ROW LEVEL SECURITY');
    }
};
