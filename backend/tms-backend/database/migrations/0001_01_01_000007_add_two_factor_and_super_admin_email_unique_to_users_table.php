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
        Schema::table('users', function (Blueprint $table) {
            // 2FA-ready: no verification logic yet, just the storage so a
            // future phase can add it without another users migration.
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });

        // The (tenant_id, email) unique constraint from the users migration
        // does not protect Super Admins: Postgres treats every NULL tenant_id
        // as distinct, so two Super Admin rows could otherwise share an
        // email. A partial unique index closes that gap for tenant_id IS NULL
        // specifically, without constraining tenant-scoped emails further.
        DB::statement('CREATE UNIQUE INDEX users_super_admin_email_unique ON users (email) WHERE tenant_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_super_admin_email_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
