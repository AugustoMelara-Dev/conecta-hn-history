<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->char('currency', 3)->nullable();
            $table->unsignedBigInteger('price_minor')->nullable();

            $table->foreign('currency', 'products_currency_fk')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'currency'], 'products_tenant_currency_idx');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->char('currency', 3)->nullable();
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->string('write_idempotency_key', 160)->nullable();
            $table->char('write_payload_hash', 64)->nullable();

            $table->foreign('currency', 'services_currency_fk')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
            $table->unique(
                ['tenant_id', 'write_idempotency_key'],
                'services_tenant_write_key_unique',
            );
        });

        Schema::table('service_providers', function (Blueprint $table): void {
            $table->string('write_idempotency_key', 160)->nullable();
            $table->char('write_payload_hash', 64)->nullable();

            $table->unique(
                ['tenant_id', 'write_idempotency_key'],
                'service_providers_tenant_write_key_unique',
            );
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->foreignUuid('location_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete();
            $table->char('currency', 3)->nullable();
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->string('service_name_snapshot')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->date('local_date')->nullable();
            $table->string('idempotency_key', 160)->nullable();
            $table->char('idempotency_hash', 64)->nullable();
            $table->string('source', 40)->nullable();
            $table->text('status_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('no_show_at')->nullable();

            $table->foreign('currency', 'appointments_currency_fk')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
            $table->foreign('timezone', 'appointments_timezone_fk')
                ->references('name')
                ->on('timezones')
                ->restrictOnDelete();
            $table->unique(
                ['tenant_id', 'idempotency_key'],
                'appointments_tenant_idempotency_unique',
            );
            $table->index(
                ['tenant_id', 'location_id', 'scheduled_at'],
                'appointments_tenant_location_start_idx',
            );
        });

        Schema::table('crm_deals', function (Blueprint $table): void {
            $table->string('write_idempotency_key', 160)->nullable();
            $table->char('write_payload_hash', 64)->nullable();

            $table->unique(
                ['tenant_id', 'write_idempotency_key'],
                'crm_deals_tenant_write_key_unique',
            );
        });

        Schema::create('service_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'service_id', 'location_id'],
                'service_locations_scope_unique',
            );
            $table->index(
                ['tenant_id', 'location_id'],
                'service_locations_tenant_location_idx',
            );
        });

        Schema::create('service_provider_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_provider_id')
                ->constrained('service_providers')
                ->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'service_provider_id', 'location_id'],
                'provider_locations_scope_unique',
            );
            $table->index(
                ['tenant_id', 'location_id'],
                'provider_locations_tenant_location_idx',
            );
        });

        Schema::create('availability_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->cascadeOnDelete();
            $table->foreignId('service_provider_id')
                ->nullable()
                ->constrained('service_providers')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->string('starts_at_local', 8);
            $table->string('ends_at_local', 8);
            $table->string('timezone', 64);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source', 32)->default('canonical');
            $table->string('source_key', 190)->nullable();
            $table->timestamps();

            $table->foreign('timezone', 'availability_rules_timezone_fk')
                ->references('name')
                ->on('timezones')
                ->restrictOnDelete();
            $table->index(
                ['tenant_id', 'location_id', 'weekday', 'is_active'],
                'availability_rules_lookup_idx',
            );
            $table->index(
                ['tenant_id', 'service_provider_id', 'weekday'],
                'availability_rules_provider_idx',
            );
            $table->unique(
                ['tenant_id', 'source_key'],
                'availability_rules_source_unique',
            );
        });

        Schema::create('availability_exceptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->cascadeOnDelete();
            $table->foreignId('service_provider_id')
                ->nullable()
                ->constrained('service_providers')
                ->cascadeOnDelete();
            $table->date('local_date');
            $table->string('kind', 16);
            $table->string('starts_at_local', 8)->nullable();
            $table->string('ends_at_local', 8)->nullable();
            $table->string('timezone', 64);
            $table->string('reason', 255)->nullable();
            $table->string('source', 32)->default('canonical');
            $table->string('source_key', 190)->nullable();
            $table->timestamps();

            $table->foreign('timezone', 'availability_exceptions_timezone_fk')
                ->references('name')
                ->on('timezones')
                ->restrictOnDelete();
            $table->index(
                ['tenant_id', 'location_id', 'local_date'],
                'availability_exceptions_lookup_idx',
            );
            $table->index(
                ['tenant_id', 'service_provider_id', 'local_date'],
                'availability_exceptions_provider_idx',
            );
            $table->unique(
                ['tenant_id', 'source_key'],
                'availability_exceptions_source_unique',
            );
        });

        Schema::create('availability_write_operations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->cascadeOnDelete();
            $table->foreignId('service_provider_id')
                ->nullable()
                ->constrained('service_providers')
                ->cascadeOnDelete();
            $table->string('idempotency_key', 160);
            $table->char('payload_hash', 64);
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'idempotency_key'],
                'availability_operations_tenant_key_unique',
            );
            $table->index(
                ['tenant_id', 'location_id', 'service_id', 'service_provider_id'],
                'availability_operations_scope_idx',
            );
        });

        Schema::create('quotes', function (Blueprint $table): void {
            $table->uuid('id');
            // Keep the primary-key command ahead of the self-referencing
            // supersedes foreign key on PostgreSQL.
            $table->primary('id');
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->foreignId('customer_profile_id')
                ->nullable()
                ->constrained('customer_profiles')
                ->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('crm_deal_id')->nullable()->constrained('crm_deals')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('supersedes_quote_id')->nullable();
            $table->string('status', 24)->default('draft');
            $table->string('source', 48)->default('merchant');
            $table->char('currency', 3);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->string('idempotency_key', 160);
            $table->char('payload_hash', 64);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('converted_appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete();
            $table->timestamps();

            $table->foreign('currency', 'quotes_currency_fk')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
            $table->foreign('supersedes_quote_id', 'quotes_supersedes_fk')
                ->references('id')
                ->on('quotes')
                ->nullOnDelete();
            $table->unique(
                ['tenant_id', 'idempotency_key'],
                'quotes_tenant_idempotency_unique',
            );
            $table->index(
                ['tenant_id', 'status', 'expires_at'],
                'quotes_tenant_status_expiry_idx',
            );
            $table->index(
                ['tenant_id', 'crm_deal_id'],
                'quotes_tenant_deal_idx',
            );
        });

        Schema::create('quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['quote_id', 'product_id'], 'quote_items_product_idx');
            $table->index(['quote_id', 'service_id'], 'quote_items_service_idx');
        });
    }

    public function down(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('availability_write_operations');
        Schema::dropIfExists('availability_exceptions');
        Schema::dropIfExists('availability_rules');
        Schema::dropIfExists('service_provider_locations');
        Schema::dropIfExists('service_locations');

        Schema::table('crm_deals', function (Blueprint $table): void {
            $table->dropUnique('crm_deals_tenant_write_key_unique');
            $table->dropColumn(['write_idempotency_key', 'write_payload_hash']);
        });

        Schema::table('appointments', function (Blueprint $table) use ($sqlite): void {
            $table->dropIndex('appointments_tenant_location_start_idx');
            $table->dropUnique('appointments_tenant_idempotency_unique');
            $table->dropForeign($sqlite ? ['currency'] : 'appointments_currency_fk');
            $table->dropForeign($sqlite ? ['timezone'] : 'appointments_timezone_fk');
            $table->dropForeign(['location_id']);
            $table->dropColumn([
                'location_id',
                'currency',
                'price_minor',
                'service_name_snapshot',
                'duration_minutes',
                'timezone',
                'local_date',
                'idempotency_key',
                'idempotency_hash',
                'source',
                'status_reason',
                'confirmed_at',
                'completed_at',
                'cancelled_at',
                'no_show_at',
            ]);
        });

        Schema::table('service_providers', function (Blueprint $table): void {
            $table->dropUnique('service_providers_tenant_write_key_unique');
            $table->dropColumn(['write_idempotency_key', 'write_payload_hash']);
        });

        Schema::table('services', function (Blueprint $table) use ($sqlite): void {
            $table->dropUnique('services_tenant_write_key_unique');
            $table->dropForeign($sqlite ? ['currency'] : 'services_currency_fk');
            $table->dropColumn([
                'currency',
                'price_minor',
                'write_idempotency_key',
                'write_payload_hash',
            ]);
        });

        Schema::table('products', function (Blueprint $table) use ($sqlite): void {
            $table->dropIndex('products_tenant_currency_idx');
            $table->dropForeign($sqlite ? ['currency'] : 'products_currency_fk');
            $table->dropColumn(['currency', 'price_minor']);
        });
    }
};
