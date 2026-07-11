<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A STORED generated column, kept in sync by Postgres itself on every
     * INSERT/UPDATE — no application code, trigger, or job ever needs to
     * remember to update it. Subject is weighted 'A' (highest), description
     * 'B', so a match in the subject ranks above the same term only
     * appearing in the description when ordering by ts_rank.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE tickets ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('english', coalesce(subject, '')), 'A') ||
                setweight(to_tsvector('english', coalesce(description, '')), 'B')
            ) STORED
        ");

        DB::statement('CREATE INDEX tickets_search_vector_index ON tickets USING GIN (search_vector)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tickets_search_vector_index');
        DB::statement('ALTER TABLE tickets DROP COLUMN IF EXISTS search_vector');
    }
};
