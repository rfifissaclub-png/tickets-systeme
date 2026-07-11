<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Same tenant-isolation pattern as the Phase 1 enable_row_level_security
     * migration (see that file for the full rationale) applied to the three
     * ticket-domain tables, all of which carry a plain `tenant_id` column.
     */
    public function up(): void
    {
        foreach (['ticket_types', 'tickets', 'ticket_comments'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");

            DB::statement("
                CREATE POLICY {$table}_tenant_isolation ON {$table}
                FOR SELECT USING (
                    tenant_id = current_setting('app.tenant_id', true)::uuid
                    OR current_setting('app.bypass_rls', true) = 'on'
                )
            ");

            DB::statement("
                CREATE POLICY {$table}_tenant_write ON {$table}
                FOR UPDATE USING (
                    tenant_id = current_setting('app.tenant_id', true)::uuid
                    OR current_setting('app.bypass_rls', true) = 'on'
                ) WITH CHECK (
                    tenant_id = current_setting('app.tenant_id', true)::uuid
                    OR current_setting('app.bypass_rls', true) = 'on'
                )
            ");

            DB::statement("
                CREATE POLICY {$table}_tenant_delete ON {$table}
                FOR DELETE USING (
                    tenant_id = current_setting('app.tenant_id', true)::uuid
                    OR current_setting('app.bypass_rls', true) = 'on'
                )
            ");

            DB::statement("
                CREATE POLICY {$table}_tenant_insert ON {$table}
                FOR INSERT WITH CHECK (
                    tenant_id = current_setting('app.tenant_id', true)::uuid
                    OR current_setting('app.bypass_rls', true) = 'on'
                )
            ");
        }

        // Business-rule immutability, enforced at the engine level as a
        // backstop to TicketStatusTransitions (see that class): once a
        // ticket is Closed, no field may change again except a soft-delete;
        // once Resolved, its content fields are read-only but its status may
        // still move on (to Closed, or reopened to InProgress). This runs
        // regardless of which application code path writes the row.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_ticket_immutability() RETURNS TRIGGER AS $$
            BEGIN
                IF OLD.status = 'closed' THEN
                    IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL THEN
                        NEW.status := OLD.status;
                        NEW.subject := OLD.subject;
                        NEW.description := OLD.description;
                        NEW.priority := OLD.priority;
                        NEW.ticket_type_id := OLD.ticket_type_id;
                        NEW.agent_id := OLD.agent_id;
                        NEW.resolved_at := OLD.resolved_at;
                        NEW.closed_at := OLD.closed_at;
                        RETURN NEW;
                    END IF;

                    IF NEW.status IS DISTINCT FROM OLD.status
                        OR NEW.subject IS DISTINCT FROM OLD.subject
                        OR NEW.description IS DISTINCT FROM OLD.description
                        OR NEW.priority IS DISTINCT FROM OLD.priority
                        OR NEW.ticket_type_id IS DISTINCT FROM OLD.ticket_type_id
                        OR NEW.agent_id IS DISTINCT FROM OLD.agent_id
                    THEN
                        RAISE EXCEPTION 'Closed tickets cannot be modified.' USING ERRCODE = 'check_violation';
                    END IF;
                END IF;

                IF OLD.status = 'resolved' AND NEW.status = 'resolved' THEN
                    IF NEW.subject IS DISTINCT FROM OLD.subject
                        OR NEW.description IS DISTINCT FROM OLD.description
                        OR NEW.priority IS DISTINCT FROM OLD.priority
                        OR NEW.ticket_type_id IS DISTINCT FROM OLD.ticket_type_id
                        OR NEW.agent_id IS DISTINCT FROM OLD.agent_id
                    THEN
                        RAISE EXCEPTION 'Resolved tickets are read-only; change the status to reopen before editing.' USING ERRCODE = 'check_violation';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER tickets_enforce_immutability
                BEFORE UPDATE ON tickets
                FOR EACH ROW
                EXECUTE FUNCTION enforce_ticket_immutability();
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS tickets_enforce_immutability ON tickets');
        DB::unprepared('DROP FUNCTION IF EXISTS enforce_ticket_immutability()');

        foreach (['ticket_types', 'tickets', 'ticket_comments'] as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_write ON {$table}");
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_delete ON {$table}");
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_insert ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
