<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('ticket_type_id')->constrained('ticket_types')->restrictOnDelete();
            $table->foreignUuid('client_id')->constrained('users')->restrictOnDelete();

            // Null while the ticket sits in the pool, unassigned.
            $table->foreignUuid('agent_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('subject');
            $table->text('description');
            $table->string('status')->default('pending');
            $table->string('priority')->default('medium');

            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'agent_id']);
            $table->index(['tenant_id', 'client_id']);
            // The ticket pool query: unassigned, pending tickets for a tenant.
            $table->index(['tenant_id', 'status', 'agent_id'], 'tickets_pool_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
