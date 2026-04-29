<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;

final class WaveThreeConsolidationGovernanceTest extends TestCase
{
    private const MIGRATION = __DIR__.'/../../../database/migrations/2026_04_15_120000_consolidate_orders_and_notifications_wave3.php';

    public function test_wave_three_alignment_consolidates_orders_with_snapshots_and_canonical_columns(): void
    {
        $source = $this->migrationSource();

        $this->assertStringContainsString("Schema::create('orders_legacy_snapshot'", $source);
        $this->assertStringContainsString("'order_id'", $source);
        $this->assertStringContainsString("'legacy_customer_address'", $source);
        $this->assertStringContainsString("'legacy_cancel_reason'", $source);
        $this->assertStringContainsString("'legacy_discount_amount'", $source);
        $this->assertStringContainsString("DB::table('orders_legacy_snapshot')->insertUsing", $source);

        $this->assertStringContainsString("'delivery_address' => DB::raw('customer_address')", $source);
        $this->assertStringContainsString("'cancellation_reason' => DB::raw('cancel_reason')", $source);
        $this->assertStringContainsString("'discount' => DB::raw('discount_amount')", $source);
        $this->assertStringContainsString("'customer_address', 'cancel_reason', 'discount_amount'", $source);
        $this->assertStringContainsString('$table->dropColumn($legacyColumns)', $source);

        $this->assertStringContainsString('orders_tenant_status_created_at_idx', $source);
        $this->assertStringContainsString("['tenant_id', 'status', 'created_at']", $source);
        $this->assertStringContainsString('orders_tenant_payment_status_idx', $source);
        $this->assertStringContainsString("['tenant_id', 'payment_status']", $source);
        $this->assertStringContainsString('orders_order_number_unique_global', $source);
        $this->assertStringContainsString("['order_number']", $source);
    }

    public function test_wave_three_alignment_consolidates_notifications_with_snapshots_and_indexes(): void
    {
        $source = $this->migrationSource();

        $this->assertStringContainsString("Schema::create('notifications_legacy_snapshot'", $source);
        $this->assertStringContainsString("'notification_id'", $source);
        $this->assertStringContainsString("'legacy_notifiable_id'", $source);
        $this->assertStringContainsString("'legacy_notifiable_type'", $source);
        $this->assertStringContainsString("DB::table('notifications_legacy_snapshot')->insertUsing", $source);
        $this->assertStringContainsString('CAST(notifiable_id AS CHAR)', $source);
        $this->assertStringContainsString('notifications_notifiable_type_notifiable_id_index', $source);
        $this->assertStringContainsString("['notifiable_type', 'notifiable_id']", $source);
    }

    private function migrationSource(): string
    {
        $source = file_get_contents(self::MIGRATION);

        $this->assertIsString($source, 'Wave three consolidation migration must be readable.');

        return $source;
    }
}
