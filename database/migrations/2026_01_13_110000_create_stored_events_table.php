<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_events', function (Blueprint $table) {
            // Global Sequence
            $table->bigIncrements('id');

            // Stream Identification
            $table->uuid('aggregate_uuid')->index();
            $table->string('aggregate_type');

            // Isolation (The Tenant-ID Rule)
            // String type to support UUIDs or Slugs. Nullable only for System Events (rare exceptions).
            $table->string('tenant_id')->nullable()->index();

            // Content
            $table->string('event_type'); // FQCN
            $table->json('payload'); // Domain Event Data

            // Metadata (The Metadata Rule)
            // Stores context: IP, UserAgent, CorrelationID, CauserID
            $table->json('meta_data')->nullable();

            // Optimistic Locking & Ordering
            $table->unsignedInteger('aggregate_version');

            // Precision Timestamp (Microseconds)
            $table->timestamp('created_at', 6)->useCurrent();

            // CONCURRENCY SHIELD:
            // Prevents branching histories.
            $table->unique(['aggregate_uuid', 'aggregate_version'], 'stored_events_optimistic_lock_unique');
            
            // PERFORMANCE INDEX:
            // Allows rapid filtering of a tenant's history.
            $table->index(['tenant_id', 'aggregate_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_events');
    }
};
