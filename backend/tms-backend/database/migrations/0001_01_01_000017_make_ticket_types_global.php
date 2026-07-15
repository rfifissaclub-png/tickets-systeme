<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ticket types stop being a per-tenant resource and become a single
     * platform-wide catalog (managed only by a Super Admin — see
     * TicketTypePolicy) shared by every tenant: an "IT Support" category
     * means the same thing everywhere instead of every tenant maintaining
     * its own copy. Drops the RLS policies the
     * enable_row_level_security_for_tickets migration created for this
     * table specifically (tickets/ticket_comments keep theirs — this only
     * ever touches ticket_types) and the tenant_id column/FK itself.
     *
     * agent_skills is untouched: "this agent is qualified for this type" is
     * still a genuinely per-tenant fact even once the type itself is
     * global, so that pivot keeps its own tenant_id.
     */
    public function up(): void
    {
        DB::statement('DROP POLICY IF EXISTS ticket_types_tenant_isolation ON ticket_types');
        DB::statement('DROP POLICY IF EXISTS ticket_types_tenant_write ON ticket_types');
        DB::statement('DROP POLICY IF EXISTS ticket_types_tenant_delete ON ticket_types');
        DB::statement('DROP POLICY IF EXISTS ticket_types_tenant_insert ON ticket_types');
        DB::statement('ALTER TABLE ticket_types NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ticket_types DISABLE ROW LEVEL SECURITY');

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'name']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->foreignUuid('tenant_id')->after('id')->constrained('tenants')->cascadeOnDelete();
        });

        // Every existing row needs a tenant before the (tenant_id, name)
        // unique constraint (and NOT NULL from the FK) can be restored —
        // there is no sensible automatic choice, so this down() only
        // restores the schema shape, not data; a real rollback would need
        // to backfill tenant_id by hand first.
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->unique(['tenant_id', 'name']);
        });

        DB::statement('ALTER TABLE ticket_types ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ticket_types FORCE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY ticket_types_tenant_isolation ON ticket_types
            FOR SELECT USING (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");

        DB::statement("
            CREATE POLICY ticket_types_tenant_write ON ticket_types
            FOR UPDATE USING (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            ) WITH CHECK (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");

        DB::statement("
            CREATE POLICY ticket_types_tenant_delete ON ticket_types
            FOR DELETE USING (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");

        DB::statement("
            CREATE POLICY ticket_types_tenant_insert ON ticket_types
            FOR INSERT WITH CHECK (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");
    }
};
