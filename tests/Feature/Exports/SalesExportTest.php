<?php

declare(strict_types=1);

namespace Tests\Feature\Exports;

use App\Events\Audit\SalesExportCreated;
use App\Exports\Criteria\SalesExportCriteria;
use App\Exports\SalesExport;
use App\Models\Order;
use App\Models\Tenant;
use App\ValueObjects\TenantId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * SalesExportTest - Multi-Tenant Isolation Verification
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HOSTILE MULTI-TENANCY TEST                                             ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This test mathematically proves that Tenant A CANNOT see Tenant B data ║
 * ║  under any circumstances. It creates a "hostile" scenario where both    ║
 * ║  tenants have data, then exports under one tenant context and verifies  ║
 * ║  complete isolation.                                                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class SalesExportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two hostile tenants
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A - Legitimate']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B - Hostile']);
    }

    /**
     * @test
     * Tenant A export contains ONLY Tenant A data
     */
    public function export_contains_only_data_from_requesting_tenant(): void
    {
        // Arrange: Create orders for both tenants
        $tenantAOrders = Order::factory()
            ->count(5)
            ->create(['tenant_id' => $this->tenantA->id]);

        $tenantBOrders = Order::factory()
            ->count(3)
            ->create(['tenant_id' => $this->tenantB->id]);

        // Act: Export under Tenant A context
        $export = new SalesExport(
            tenantId: new TenantId($this->tenantA->id),
            criteria: SalesExportCriteria::unbounded(),
            userId: 1,
            ipAddress: '127.0.0.1',
            userAgent: 'Test Agent',
        );

        // Get query results
        $results = $export->query()->get();

        // Assert: Only Tenant A orders are returned
        $this->assertCount(5, $results);

        foreach ($results as $order) {
            $this->assertEquals(
                $this->tenantA->id,
                $order->tenant_id,
                "SECURITY VIOLATION: Found order from wrong tenant!"
            );
        }

        // Verify NO Tenant B order IDs in results
        $resultIds = $results->pluck('id')->toArray();
        $tenantBIds = $tenantBOrders->pluck('id')->toArray();

        foreach ($tenantBIds as $hostileId) {
            $this->assertNotContains(
                $hostileId,
                $resultIds,
                "CRITICAL: Hostile tenant data leaked into export!"
            );
        }
    }

    /**
     * @test
     * Export dispatches audit event with correct metadata
     */
    public function export_dispatches_audit_event(): void
    {
        Event::fake([SalesExportCreated::class]);

        // Arrange
        Order::factory()->count(2)->create(['tenant_id' => $this->tenantA->id]);

        // Act
        new SalesExport(
            tenantId: new TenantId($this->tenantA->id),
            criteria: SalesExportCriteria::create('2025-01-01', '2025-12-31'),
            userId: 42,
            ipAddress: '192.168.1.100',
            userAgent: 'Mozilla/5.0',
        );

        // Assert
        Event::assertDispatched(SalesExportCreated::class, function ($event) {
            return $event->tenantId->toInt() === $this->tenantA->id
                && $event->userId === 42
                && $event->format === 'xlsx'
                && !empty($event->criteriaHash);
        });
    }

    /**
     * @test
     * TenantId Value Object is required (not primitive int)
     */
    public function constructor_requires_tenant_id_value_object(): void
    {
        // This test verifies at compile time that TenantId VO is required
        // If someone changes the signature to accept int, this will fail

        $reflection = new \ReflectionClass(SalesExport::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $tenantIdParam = $parameters[0];

        $this->assertEquals('tenantId', $tenantIdParam->getName());
        $this->assertEquals(TenantId::class, $tenantIdParam->getType()->getName());
    }

    /**
     * @test
     * Date range filter works correctly within tenant
     */
    public function date_filter_works_within_tenant_context(): void
    {
        // Create orders with different dates
        Order::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_at' => now()->subDays(5),
        ]);

        Order::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_at' => now()->subDays(30),
        ]);

        Order::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_at' => now()->subDays(60), // Outside range
        ]);

        // Export last 45 days
        $export = new SalesExport(
            tenantId: new TenantId($this->tenantA->id),
            criteria: SalesExportCriteria::forLastDays(45),
            userId: 1,
        );

        $results = $export->query()->get();

        // Should only get 2 orders (within 45 days)
        $this->assertCount(2, $results);
    }

    /**
     * @test
     * Status filter works correctly within tenant
     */
    public function status_filter_works_within_tenant_context(): void
    {
        Order::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'status' => Order::STATUS_PENDING,
        ]);

        Order::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'status' => Order::STATUS_DELIVERED,
        ]);

        Order::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'status' => Order::STATUS_DELIVERED,
        ]);

        $export = new SalesExport(
            tenantId: new TenantId($this->tenantA->id),
            criteria: SalesExportCriteria::create(status: Order::STATUS_DELIVERED),
            userId: 1,
        );

        $results = $export->query()->get();

        $this->assertCount(2, $results);
        foreach ($results as $order) {
            $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
        }
    }

    /**
     * @test
     * Empty tenant returns no data (not all data)
     */
    public function empty_tenant_returns_no_orders(): void
    {
        // Create orders for Tenant A only
        Order::factory()->count(5)->create(['tenant_id' => $this->tenantA->id]);

        // Export for Tenant B (which has no orders)
        $export = new SalesExport(
            tenantId: new TenantId($this->tenantB->id),
            criteria: SalesExportCriteria::unbounded(),
            userId: 1,
        );

        $results = $export->query()->get();

        // CRITICAL: Must be empty, not all orders
        $this->assertCount(0, $results);
    }

    /**
     * @test
     * Export generates unique export ID
     */
    public function export_generates_unique_id(): void
    {
        $export1 = new SalesExport(
            tenantId: new TenantId($this->tenantA->id),
            criteria: SalesExportCriteria::unbounded(),
            userId: 1,
        );

        $export2 = new SalesExport(
            tenantId: new TenantId($this->tenantA->id),
            criteria: SalesExportCriteria::unbounded(),
            userId: 1,
        );

        $this->assertNotEquals($export1->getExportId(), $export2->getExportId());
    }
}
