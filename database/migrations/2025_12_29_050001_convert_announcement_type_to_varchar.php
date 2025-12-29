<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convert announcement type from ENUM to VARCHAR.
 *
 * ADR-002: DOMAIN TYPES
 * ═══════════════════════════════════════════════════════════════════════════
 * CONTEXT:
 *   MySQL ENUM requires ALTER TABLE to add values, which is:
 *   - Slow on large tables (table lock)
 *   - Error-prone (typos in migration)
 *   - Not portable across databases
 *
 * DECISION:
 *   - Use VARCHAR(32) in database
 *   - Define valid values in PHP: App\Enums\AnnouncementType
 *   - Validate in: Request, Factory, Model mutator
 *
 * ALTERNATIVES REJECTED:
 *   1. Keep ENUM: Migration risk, portability issues
 *   2. Integer codes: Poor readability, magic numbers
 *
 * CONSEQUENCES:
 *   - Must add validation in application layer
 *   - New types only require PHP code change
 *   - Database accepts any string (app validates)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ADDING NEW TYPES:
 *   1. Add constant to App\Enums\AnnouncementType
 *   2. Update validation rules in AnnouncementRequest
 *   3. Update factory states if needed
 *   4. NO MIGRATION REQUIRED
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support column type changes directly
        // This migration is MySQL-specific
        if (config('database.default') === 'sqlite') {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            $table->string('type', 32)->default('info')->change();
        });
    }

    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            $table->enum('type', ['info', 'warning', 'promo'])->default('info')->change();
        });
    }
};
