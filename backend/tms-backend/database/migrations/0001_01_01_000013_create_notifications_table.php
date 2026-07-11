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
     * A custom table, not Laravel's stock `notifications` migration: the
     * spec requires notifications to be tenant-owned and RLS-protected like
     * everything else, and the built-in DatabaseNotification model/channel
     * has no concept of tenant_id. See App\Notifications\Channels\TenantDatabaseChannel.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('notifiable_type');
            $table->uuid('notifiable_id');
            $table->string('type');
            $table->jsonb('data');
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'notifiable_type', 'notifiable_id', 'read_at'], 'notifications_recipient_unread_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
