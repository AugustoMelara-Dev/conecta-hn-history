<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('activity_log')) {
            foreach (['subject_id', 'causer_id'] as $column) {
                if (Schema::hasColumn('activity_log', $column)) {
                    DB::statement("ALTER TABLE activity_log MODIFY {$column} VARCHAR(36) NULL");
                }
            }
        }

        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'primary_color')) {
            DB::statement('ALTER TABLE tenants MODIFY primary_color VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        // Intentionally left non-destructive: narrowing these columns can truncate UUID audit
        // references or malicious color payloads used by security regression tests.
    }
};
