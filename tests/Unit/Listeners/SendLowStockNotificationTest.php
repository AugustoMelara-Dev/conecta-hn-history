<?php

declare(strict_types=1);

use App\Events\Inventory\LowStockThresholdReached;
use App\Listeners\SendLowStockNotification;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crear dos tenants
    $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
    $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

    // Crear productos - uno para cada tenant
    $this->productA = Product::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Product A',
        'stock_quantity' => 5,
    ]);

    $this->productB = Product::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Product B',
        'stock_quantity' => 3,
    ]);

    $this->listener = new SendLowStockNotification;

    Notification::fake();
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: SUCCESSFUL PROCESSING
// ═══════════════════════════════════════════════════════════════════════════

test('processes product with correct tenant_id', function () {
    $event = new LowStockThresholdReached(
        product: $this->productA,
        currentStock: 5,
        threshold: 10
    );

    // No debería lanzar excepciones
    $this->listener->handle($event);

    // Test pasa si no hay excepciones
    expect(true)->toBeTrue();
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: CROSS-TENANT ACCESS BLOCKED
// ═══════════════════════════════════════════════════════════════════════════

test('blocks product from different tenant and logs security event', function () {
    // Simular evento malintencionado: producto de TenantB pero con contexto manipulado
    // Creamos un producto "falso" que simula un ID de otro tenant
    // pero se procesa a nivel de código
    $maliciousProduct = Mockery::mock(\App\Models\Product::class)->makePartial();
    $maliciousProduct->id = $this->productA->id;
    $maliciousProduct->tenant_id = $this->tenantB->id;

    $maliciousEvent = new LowStockThresholdReached(
        product: $maliciousProduct,
        currentStock: 5,
        threshold: 10
    );

    Log::shouldReceive('channel')
        ->with('security')
        ->once()
        ->andReturnSelf();

    Log::shouldReceive('critical')
        ->once()
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'Cross-Tenant Access Attempt BLOCKED') &&
                   $context['severity'] === 'CRITICAL';
        });

    // El listener debe retornar silenciosamente (Fail Silent Protocol)
    $this->listener->handle($maliciousEvent);
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: NON-EXISTENT PRODUCT
// ═══════════════════════════════════════════════════════════════════════════

test('gracefully returns for non-existent product', function () {
    $maliciousProduct = Mockery::mock(\App\Models\Product::class)->makePartial();
    $maliciousProduct->id = '99999999';
    $maliciousProduct->tenant_id = $this->tenantA->id;

    $maliciousEvent = new LowStockThresholdReached(
        product: $maliciousProduct,
        currentStock: 5,
        threshold: 10
    );

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'not found'));

    // No debe lanzar excepciones (Fail Silent)
    $this->listener->handle($maliciousEvent);

    expect(true)->toBeTrue();
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: NO EXCEPTIONS IN BACKGROUND JOBS (FAIL SILENT)
// ═══════════════════════════════════════════════════════════════════════════

test('never throws exceptions - implements Fail Silent protocol', function () {
    $scenarios = [
        ['aggregateId' => '999999', 'tenantId' => (string) $this->tenantA->id],
        ['aggregateId' => (string) $this->productA->id, 'tenantId' => 'invalid-uuid'],
        ['aggregateId' => '', 'tenantId' => ''],
    ];

    Log::shouldReceive('channel')->andReturnSelf();
    Log::shouldReceive('critical')->andReturnNull();
    Log::shouldReceive('warning')->andReturnNull();

    foreach ($scenarios as $scenario) {
        $fakeProduct = Mockery::mock(\App\Models\Product::class)->makePartial();
        $fakeProduct->id = $scenario['aggregateId'];
        $fakeProduct->tenant_id = $scenario['tenantId'];

        $event = new LowStockThresholdReached(
            product: $fakeProduct,
            currentStock: 5,
            threshold: 10
        );

        // No debe lanzar ninguna excepción
        expect(fn () => $this->listener->handle($event))->not->toThrow(Exception::class);
    }
});
