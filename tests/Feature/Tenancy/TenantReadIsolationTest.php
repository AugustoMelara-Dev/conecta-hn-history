<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domain\Product\Contracts\ProductReader;
use App\Domain\Sales\Contracts\ReportReader;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class TenantReadIsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sales_report_reader_only_returns_orders_for_the_current_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $tenantAOrder = Order::factory()
            ->forTenant($tenantA)
            ->create([
                'order_number' => '#TENANT-A-REPORT',
                'status' => Order::STATUS_DELIVERED,
                'total' => 325.50,
                'created_at' => now()->subDay(),
            ]);

        Order::factory()
            ->forTenant($tenantB)
            ->create([
                'order_number' => '#TENANT-B-REPORT',
                'status' => Order::STATUS_DELIVERED,
                'total' => 999.99,
                'created_at' => now()->subDay(),
            ]);

        $reader = $this->app->make(ReportReader::class);
        $startDate = new DateTimeImmutable(now()->subDays(3)->startOfDay()->toAtomString());
        $endDate = new DateTimeImmutable(now()->endOfDay()->toAtomString());

        $orders = $reader->getSalesData((string) $tenantA->id, $startDate, $endDate);
        $summary = $reader->getSalesSummary((string) $tenantA->id, $startDate, $endDate, $orders);
        $orderNumbers = array_map(
            static fn (array $order): string => (string) ($order['order_number'] ?? ''),
            $orders,
        );

        self::assertContains($tenantAOrder->order_number, $orderNumbers);
        self::assertNotContains('#TENANT-B-REPORT', $orderNumbers);
        self::assertGreaterThanOrEqual(1, $summary['order_count']);
        self::assertGreaterThanOrEqual(325.5, (float) $summary['total_revenue']);
    }

    public function test_inventory_reader_only_returns_products_for_the_current_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $tenantAProduct = Product::factory()
            ->forTenant($tenantA)
            ->create([
                'name' => 'Producto visible A',
            ]);

        Product::factory()
            ->forTenant($tenantB)
            ->create([
                'name' => 'Producto visible B',
            ]);

        $reader = $this->app->make(ProductReader::class);
        $products = $reader->getInventory((string) $tenantA->id);
        $productNames = array_map(
            static fn (array $product): string => (string) ($product['name'] ?? ''),
            $products,
        );

        self::assertContains('Producto visible A', $productNames);
        self::assertNotContains('Producto visible B', $productNames);
        self::assertContains($tenantAProduct->id, array_map(
            static fn (array $product): int => (int) ($product['id'] ?? 0),
            $products,
        ));
    }
}
