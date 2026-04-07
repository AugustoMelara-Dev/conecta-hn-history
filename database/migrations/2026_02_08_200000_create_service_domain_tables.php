<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service Domain Tables
 *
 * Implements the "SERVICE" business type entities:
 * - service_providers: Staff/resources that perform services
 * - services: Catalog of bookable services (time-based inventory)
 * - appointments: Customer bookings linking service + provider + time
 *
 * INVARIANTS:
 * - All tables tenant-isolated via tenant_id FK
 * - Prices stored as decimal(10,2) for currency precision
 * - end_at denormalized for efficient range queries
 *
 * INTEGRATIONS:
 * - spatie/opening-hours via schedule_config JSON field
 * - User model for customer relationship
 */
return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // SERVICE PROVIDERS - Staff/Resources (Who performs the service)
        // ═══════════════════════════════════════════════════════════════════════
        if (! Schema::hasTable('service_providers')) {
            Schema::create('service_providers', function (Blueprint $table) {
                $table->id();
                $table->char('tenant_id', 36); // UUID FK
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->json('schedule_config')->nullable(); // spatie/opening-hours overrides
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        // ═══════════════════════════════════════════════════════════════════════
        // SERVICES - Catalog (What can be booked)
        // ═══════════════════════════════════════════════════════════════════════
        if (! Schema::hasTable('services')) {
            Schema::create('services', function (Blueprint $table) {
                $table->id();
                $table->char('tenant_id', 36); // UUID FK
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->integer('duration_minutes')->default(30);
                $table->decimal('price', 10, 2);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        // ═══════════════════════════════════════════════════════════════════════
        // APPOINTMENTS - Bookings (When service is performed)
        // ═══════════════════════════════════════════════════════════════════════
        if (! Schema::hasTable('appointments')) {
            Schema::create('appointments', function (Blueprint $table) {
                $table->id();
                $table->char('tenant_id', 36); // UUID FK
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

                // Customer (nullable for walk-ins)
                $table->char('user_id', 36)->nullable(); // UUID FK
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

                $table->foreignId('service_id')->constrained()->cascadeOnDelete();
                $table->foreignId('service_provider_id')->constrained()->cascadeOnDelete();

                $table->dateTime('scheduled_at');
                $table->dateTime('end_at'); // Denormalized for range queries
                $table->string('status')->default('pending'); // pending, confirmed, cancelled, completed

                $table->string('customer_name')->nullable(); // For guest bookings
                $table->string('customer_phone')->nullable();
                $table->text('customer_notes')->nullable();
                $table->text('staff_notes')->nullable();

                $table->timestamps();

                // Calendar view optimization
                $table->index(['tenant_id', 'scheduled_at']);
                // Conflict detection
                $table->index(['service_provider_id', 'scheduled_at', 'end_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_providers');
    }
};
