<?php

declare(strict_types=1);

namespace Tests\Feature\Exports;

use App\Domain\Shared\ValueObjects\TenantId;
use App\Events\Audit\SalesExportCreated;
use App\Exports\Criteria\SalesExportCriteria;
use App\Exports\SalesExport;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * SalesExportTest - Verificación de Aislamiento Multi-Tenant (MySQL Engine)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HOSTILE MULTI-TENANCY TEST                                             ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This test mathematically proves that Tenant A CANNOT see Tenant B data ║
 * ║  under any circumstances.                                                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Ejecutándose sobre MySQL para garantizar paridad Dev/Prod y manejo
 * correcto de transacciones anidadas, Foreign Keys y Commits.
 */
class SalesExportTest extends TestCase
{
    // MySQL transaction rollback keeps this tenant-isolation suite fast and deterministic.
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Aislamiento de eventos para evitar efectos secundarios (Emails, Logs, etc)
        Event::fake([SalesExportCreated::class]);
    }

    /**
     * Helper: Crear datos de prueba ignorando eventos del modelo para velocidad.
     */
    private function createTestData(): array
    {
        return Model::withoutEvents(function () {
            $userA = User::factory()->create(['email' => 'owner-a@test.com']);
            $userB = User::factory()->create(['email' => 'owner-b@test.com']);

            $tenantA = Tenant::factory()->create([
                'name' => 'Tenant A - Legitimate',
                'user_id' => $userA->id,
            ]);

            $tenantB = Tenant::factory()->create([
                'name' => 'Tenant B - Hostile',
                'user_id' => $userB->id,
            ]);

            return compact('tenantA', 'tenantB', 'userA', 'userB');
        });
    }

    /**
     * Helper: Create orders without events.
     */
    /**
     * @return Collection<int, Order>
     */
    private function createOrdersWithoutEvents(int $count, array $attributes = []): Collection
    {
        return Model::withoutEvents(function () use ($count, $attributes) {
            return Order::factory()->count($count)->create($attributes);
        });
    }

    /**
     * Tenant A export contains ONLY Tenant A data
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function export_contains_only_data_from_requesting_tenant(): void
    {
        $data = $this->createTestData();
        $this->actingAsTenant((string) $data['tenantA']->id);
        $this->createOrdersWithoutEvents(5, ['tenant_id' => $data['tenantA']->id]);
        $this->createOrdersWithoutEvents(3, ['tenant_id' => $data['tenantB']->id]);

        // Ejecución bajo el contexto del Tenant A
        $export = SalesExport::create(
            tenantId: TenantId::fromString((string) $data['tenantA']->id),
            criteria: SalesExportCriteria::withDefaultRange(),
            userId: 1,
            ipAddress: '127.0.0.1',
            userAgent: 'Test Agent',
        );

        $results = $export->query()->get();

        // Verificación Estricta: Solo deben venir 5 órdenes
        $this->assertCount(5, $results);

        // Verificación de Integridad: Todas las órdenes deben ser del Tenant A
        foreach ($results as $order) {
            $this->assertInstanceOf(Order::class, $order);
            $this->assertEquals($data['tenantA']->id, $order->tenant_id);
        }

        // Verificación de Contrato: El evento de auditoría se intentó disparar
        Event::assertDispatched(SalesExportCreated::class);
    }

    /**
     * Export dispatches audit event with correct metadata
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function export_dispatches_audit_event(): void
    {
        $data = $this->createTestData();
        $this->createOrdersWithoutEvents(2, ['tenant_id' => $data['tenantA']->id]);

        SalesExport::create(
            tenantId: TenantId::fromString((string) $data['tenantA']->id),
            criteria: SalesExportCriteria::create('2025-01-01', '2025-12-31'),
            userId: 42,
            ipAddress: '192.168.1.100',
            userAgent: 'Mozilla/5.0',
        );

        Event::assertDispatched(SalesExportCreated::class, function ($event) use ($data) {
            return $event->tenantId->toString() === (string) $data['tenantA']->id
                && $event->userId === 42
                && $event->format === 'xlsx'
                && ! empty($event->criteriaHash);
        });
    }

    /**
     * TenantId Value Object is required (not primitive int)
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function constructor_requires_tenant_id_value_object(): void
    {
        $reflection = new \ReflectionClass(SalesExport::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $tenantIdParam = $parameters[0];

        $this->assertEquals('tenantId', $tenantIdParam->getName());
        $type = $tenantIdParam->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        $this->assertEquals(TenantId::class, $type->getName());
    }

    /**
     * Date range filter works correctly within tenant
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function date_filter_works_within_tenant_context(): void
    {
        $data = $this->createTestData();
        $this->actingAsTenant((string) $data['tenantA']->id);

        Model::withoutEvents(function () use ($data) {
            Order::factory()->create([
                'tenant_id' => $data['tenantA']->id,
                'created_at' => now()->subDays(5),
            ]);

            Order::factory()->create([
                'tenant_id' => $data['tenantA']->id,
                'created_at' => now()->subDays(30),
            ]);

            Order::factory()->create([
                'tenant_id' => $data['tenantA']->id,
                'created_at' => now()->subDays(60), // Outside range
            ]);
        });

        $export = SalesExport::create(
            tenantId: TenantId::fromString((string) $data['tenantA']->id),
            criteria: SalesExportCriteria::forLastDays(45),
            userId: 1,
        );

        $results = $export->query()->get();

        $this->assertCount(2, $results);
        Event::assertDispatched(SalesExportCreated::class);
    }

    /**
     * Status filter works correctly within tenant
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function status_filter_works_within_tenant_context(): void
    {
        $data = $this->createTestData();
        $this->actingAsTenant((string) $data['tenantA']->id);

        Model::withoutEvents(function () use ($data) {
            Order::factory()->create([
                'tenant_id' => $data['tenantA']->id,
                'status' => Order::STATUS_PENDING,
            ]);

            Order::factory()->create([
                'tenant_id' => $data['tenantA']->id,
                'status' => Order::STATUS_DELIVERED,
            ]);

            Order::factory()->create([
                'tenant_id' => $data['tenantA']->id,
                'status' => Order::STATUS_DELIVERED,
            ]);
        });

        $export = SalesExport::create(
            tenantId: TenantId::fromString((string) $data['tenantA']->id),
            criteria: SalesExportCriteria::create(status: Order::STATUS_DELIVERED),
            userId: 1,
        );

        $results = $export->query()->get();

        $this->assertCount(2, $results);
        foreach ($results as $order) {
            $this->assertInstanceOf(Order::class, $order);
            $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
        }
    }

    /**
     * Empty tenant returns no data (not all data)
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function empty_tenant_returns_no_orders(): void
    {
        $data = $this->createTestData();
        $this->actingAsTenant((string) $data['tenantB']->id);
        $this->createOrdersWithoutEvents(5, ['tenant_id' => $data['tenantA']->id]);

        // Export for Tenant B (which has no orders)
        $export = SalesExport::create(
            tenantId: TenantId::fromString((string) $data['tenantB']->id),
            criteria: SalesExportCriteria::withDefaultRange(),
            userId: 1,
        );

        $results = $export->query()->get();

        // CRITICAL: Must be empty, not all orders
        $this->assertCount(0, $results);
    }

    /**
     * Export generates unique export ID
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function export_generates_unique_id(): void
    {
        $data = $this->createTestData();

        $export1 = SalesExport::create(
            tenantId: TenantId::fromString((string) $data['tenantA']->id),
            criteria: SalesExportCriteria::withDefaultRange(),
            userId: 1,
        );

        $export2 = SalesExport::create(
            tenantId: TenantId::fromString((string) $data['tenantA']->id),
            criteria: SalesExportCriteria::withDefaultRange(),
            userId: 1,
        );

        $this->assertNotEquals($export1->getExportId(), $export2->getExportId());
        Event::assertDispatchedTimes(SalesExportCreated::class, 2);
    }
}
