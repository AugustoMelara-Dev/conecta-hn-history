<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_touchpoint_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_touchpoint_id')->constrained('customer_touchpoints')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('event_type', 64);
            $table->string('status', 32)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(
                ['customer_touchpoint_id', 'provider', 'event_type', 'occurred_at'],
                'customer_touchpoint_events_dedupe'
            );
            $table->index(['tenant_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_touchpoint_events');
    }
};
