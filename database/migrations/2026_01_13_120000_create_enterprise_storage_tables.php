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
        if (! Schema::hasTable('outbox_messages')) {
            Schema::create('outbox_messages', function (Blueprint $table) {
                $table->bigIncrements('id'); // Sequential Ordering
                $table->string('type'); // Event FQCN
                $table->json('payload'); // Event Body
                
                // STATUS POINTER:
                // NULL = Pending. Timestamp = Processed.
                $table->timestamp('published_at')->nullable()->index();
                
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshots');
        Schema::dropIfExists('outbox_messages');
    }
};
