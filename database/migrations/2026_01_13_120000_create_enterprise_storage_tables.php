<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. SNAPSHOTS (The Snapshot Rule)
        // 1. SNAPSHOTS (The Snapshot Rule)
        if (! Schema::hasTable('snapshots')) {
            Schema::create('snapshots', function (Blueprint $table) {
                $table->uuid('aggregate_uuid')->primary(); // Quick Access Strategy
                $table->string('aggregate_type');
                $table->unsignedInteger('aggregate_version');
                $table->json('state'); // Compressed State
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 2. TRANSACTIONAL OUTBOX (The Outbox Rule)
        // NOTE: Canonical schema created in 2026_01_12_142300_create_titanium_support_tables.php
        // This migration is kept for backward compatibility but does nothing if table exists.
        if (Schema::hasTable('outbox_messages')) {
            return; // Table already exists with canonical schema
        }

        // Fallback: Create if somehow titanium migration was skipped
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->json('payload');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('status')->default('PENDING')->index();
            $table->timestamps();
            $table->index(['status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshots');
        Schema::dropIfExists('outbox_messages');
    }
};
