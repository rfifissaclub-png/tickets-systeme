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
        // gen_random_uuid() — used as the default for every UUID primary key.
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS "pgcrypto"');
    }
};
