<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create announcements table for scheduled marketing banners.
 *
 * ADR-001: TEMPORAL DOMAIN
 * ═══════════════════════════════════════════════════════════════════════════
 * - Datetime columns use _utc suffix to indicate UTC storage
 * - All business logic uses Carbon::utc() for queries
 * - Local timezone conversion happens in presentation layer
 *
 * ADR-002: DOMAIN TYPES
 * ═══════════════════════════════════════════════════════════════════════════
 * - Type column uses VARCHAR, not ENUM
 * - Valid values defined in App\Enums\AnnouncementType
 * - Adding new types requires only PHP code change
 *
 * ADR-003: INDEX STRATEGY
 * ═══════════════════════════════════════════════════════════════════════════
 * INDEX: idx_active_announcements
 * Optimizes the following query:
 *   WHERE business_id = ?
 *     AND is_active = 1
 *     AND (starts_at_utc IS NULL OR starts_at_utc <= ?)
 *     AND (expires_at_utc IS NULL OR expires_at_utc >= ?)
 *   ORDER BY created_at DESC
 *
 * ⚠️ CHANGES TO WHERE CLAUSE REQUIRE INDEX REVIEW
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();

            // Business isolation
            $table->foreignId('business_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // ADR-002: VARCHAR not ENUM (see App\Enums\AnnouncementType)
            $table->string('type', 32)->default('info');

            // Content (sanitized via Model::getContentHtmlAttribute())
            $table->text('content');

            // ADR-001: Scheduling in UTC (nullable = always visible)
            $table->dateTime('starts_at_utc')->nullable();
            $table->dateTime('expires_at_utc')->nullable();

            // Quick toggle
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // ADR-003: Composite index for active announcements query
            $table->index(
                ['business_id', 'is_active', 'starts_at_utc', 'expires_at_utc'],
                'idx_active_announcements'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
