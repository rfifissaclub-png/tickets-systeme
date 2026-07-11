<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backs the cahier-des-charges "Pool filtered by skill" requirement: an
     * agent is only qualified to pull tickets of the ticket types listed
     * here. A missing row means "not qualified", not "qualified for
     * everything" — TicketPoolService fails closed the same way every other
     * RLS-protected table in this app does.
     */
    public function up(): void
    {
        Schema::create('agent_skills', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('ticket_type_id')->constrained('ticket_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'ticket_type_id']);
            $table->index(['tenant_id', 'user_id']);
        });

        // Same tenant-only RLS pattern as every other tenant-owned table
        // (see the Phase 1/3 RLS migrations) — no notifications-style extra
        // owner scoping needed, skills aren't private the way an inbox is.
        DB::statement('ALTER TABLE agent_skills ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE agent_skills FORCE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY agent_skills_tenant_isolation ON agent_skills
            FOR SELECT USING (
                tenant_id = current_setting('app.tenant_id', true)::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");

        DB::statement("
            CREATE POLICY agent_skills_tenant_write ON agent_skills
            FOR UPDATE USING (
                tenant_id = current_setting('app.tenant_id', true)::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            ) WITH CHECK (
                tenant_id = current_setting('app.tenant_id', true)::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");

        DB::statement("
            CREATE POLICY agent_skills_tenant_delete ON agent_skills
            FOR DELETE USING (
                tenant_id = current_setting('app.tenant_id', true)::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");

        DB::statement("
            CREATE POLICY agent_skills_tenant_insert ON agent_skills
            FOR INSERT WITH CHECK (
                tenant_id = current_setting('app.tenant_id', true)::uuid
                OR current_setting('app.bypass_rls', true) = 'on'
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_skills');
    }
};
