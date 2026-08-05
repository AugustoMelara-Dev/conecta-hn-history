<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class BusinessOperationsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_04_expands_services_availability_reservations_and_opportunities(): void
    {
        $this->assertTableColumns('products', [
            'currency',
            'price_minor',
        ]);
        $this->assertTableColumns('services', [
            'currency',
            'price_minor',
            'write_idempotency_key',
            'write_payload_hash',
        ]);
        $this->assertTableColumns('service_providers', [
            'write_idempotency_key',
            'write_payload_hash',
        ]);
        $this->assertTableColumns('appointments', [
            'location_id',
            'service_name_snapshot',
            'duration_minutes',
            'currency',
            'price_minor',
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
        $this->assertTableColumns('crm_deals', [
            'write_idempotency_key',
            'write_payload_hash',
        ]);
        $this->assertTableColumns('availability_rules', [
            'id',
            'tenant_id',
            'location_id',
            'service_id',
            'service_provider_id',
            'weekday',
            'starts_at_local',
            'ends_at_local',
            'timezone',
            'effective_from',
            'effective_until',
            'is_active',
            'source',
            'source_key',
        ]);
        $this->assertTableColumns('availability_exceptions', [
            'id',
            'tenant_id',
            'location_id',
            'service_id',
            'service_provider_id',
            'local_date',
            'kind',
            'starts_at_local',
            'ends_at_local',
            'timezone',
            'reason',
            'source',
            'source_key',
        ]);
        $this->assertTableColumns('availability_write_operations', [
            'id',
            'tenant_id',
            'location_id',
            'service_id',
            'service_provider_id',
            'idempotency_key',
            'payload_hash',
        ]);
        $this->assertTableColumns('service_locations', [
            'tenant_id',
            'service_id',
            'location_id',
        ]);
        $this->assertTableColumns('service_provider_locations', [
            'tenant_id',
            'service_provider_id',
            'location_id',
        ]);
    }

    public function test_program_04_adds_a_durable_quote_aggregate(): void
    {
        $this->assertTableColumns('quotes', [
            'id',
            'tenant_id',
            'public_id',
            'customer_profile_id',
            'lead_id',
            'crm_deal_id',
            'location_id',
            'created_by_user_id',
            'supersedes_quote_id',
            'status',
            'source',
            'currency',
            'version',
            'subtotal_minor',
            'discount_minor',
            'tax_minor',
            'total_minor',
            'idempotency_key',
            'payload_hash',
            'issued_at',
            'expires_at',
            'accepted_at',
            'rejected_at',
            'cancelled_at',
            'converted_order_id',
            'converted_appointment_id',
        ]);
        $this->assertTableColumns('quote_items', [
            'id',
            'quote_id',
            'product_id',
            'service_id',
            'description',
            'quantity',
            'unit_price_minor',
            'discount_minor',
            'tax_minor',
            'line_total_minor',
            'metadata',
        ]);
    }

    public function test_program_04_rollback_preserves_existing_operational_data(): void
    {
        $migration = require database_path('migrations/2026_07_28_000004_expand_business_operations.php');

        $migration->down();

        foreach ([
            'products',
            'services',
            'service_providers',
            'appointments',
            'orders',
            'order_items',
            'crm_pipelines',
            'crm_stages',
            'crm_deals',
            'catalog_import_batches',
            'catalog_import_items',
        ] as $table) {
            self::assertTrue(Schema::hasTable($table), "Expected legacy operational table [{$table}] to remain.");
        }

        self::assertFalse(Schema::hasTable('quotes'));
        self::assertFalse(Schema::hasTable('quote_items'));
        self::assertFalse(Schema::hasTable('availability_rules'));
        self::assertFalse(Schema::hasTable('availability_exceptions'));
        self::assertFalse(Schema::hasTable('availability_write_operations'));
        self::assertFalse(Schema::hasColumn('appointments', 'idempotency_key'));
        self::assertFalse(Schema::hasColumn('services', 'currency'));

        $migration->up();
    }

    /**
     * @param  list<string>  $columns
     */
    private function assertTableColumns(string $table, array $columns): void
    {
        self::assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");

        foreach ($columns as $column) {
            self::assertTrue(
                Schema::hasColumn($table, $column),
                "Expected [{$table}.{$column}] to exist.",
            );
        }
    }
}
