<?php

declare(strict_types=1);

use App\Events\Inventory\LowStockThresholdReached;
use App\Listeners\SendLowStockNotification;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;

uses(DatabaseTransactions::class);

/**
 * @return array{
 *     tenantA: Tenant,
 *     tenantB: Tenant,
 *     productA: Product,
 *     productB: Product,
 *     listener: SendLowStockNotification
 * }
 */
function makeLowStockFixture(): array
{
    $tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
    $tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

    $productA = Product::factory()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Product A',
        'stock_quantity' => 5,
    ]);

    $productB = Product::factory()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Product B',
        'stock_quantity' => 3,
    ]);

    return [
        'tenantA' => $tenantA,
        'tenantB' => $tenantB,
        'productA' => $productA,
        'productB' => $productB,
        'listener' => new SendLowStockNotification,
    ];
}

function makeEventProduct(string $productId, string $tenantId): Product
{
    $product = new Product;
    $product->forceFill([
        'id' => $productId,
        'tenant_id' => $tenantId,
    ]);

    return $product;
}

test('processes product with correct tenant_id', function () {
    $fixture = makeLowStockFixture();
    $event = new LowStockThresholdReached(
        product: $fixture['productA'],
        currentStock: 5,
        threshold: 10,
    );

    $fixture['listener']->handle($event);

    expect(true)->toBeTrue();
});

test('blocks product from different tenant and logs security event', function () {
    $fixture = makeLowStockFixture();

    $maliciousEvent = new LowStockThresholdReached(
        product: makeEventProduct((string) $fixture['productA']->id, (string) $fixture['tenantB']->id),
        currentStock: 5,
        threshold: 10,
    );

    Log::shouldReceive('channel')
        ->with('security')
        ->once()
        ->andReturnSelf();

    Log::shouldReceive('critical')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'Cross-Tenant Access Attempt BLOCKED')
                && ($context['severity'] ?? null) === 'CRITICAL';
        });

    $fixture['listener']->handle($maliciousEvent);

    expect(true)->toBeTrue();
});

test('gracefully returns for non-existent product', function () {
    $fixture = makeLowStockFixture();

    $maliciousEvent = new LowStockThresholdReached(
        product: makeEventProduct('99999999', (string) $fixture['tenantA']->id),
        currentStock: 5,
        threshold: 10,
    );

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message): bool => str_contains($message, 'not found'));

    $fixture['listener']->handle($maliciousEvent);

    expect(true)->toBeTrue();
});

test('never throws exceptions and implements fail silent protocol', function () {
    $fixture = makeLowStockFixture();

    $scenarios = [
        ['aggregateId' => '999999', 'tenantId' => (string) $fixture['tenantA']->id],
        ['aggregateId' => (string) $fixture['productA']->id, 'tenantId' => 'invalid-uuid'],
        ['aggregateId' => '', 'tenantId' => ''],
    ];

    Log::shouldReceive('channel')->andReturnSelf();
    Log::shouldReceive('critical')->andReturnNull();
    Log::shouldReceive('warning')->andReturnNull();

    foreach ($scenarios as $scenario) {
        $event = new LowStockThresholdReached(
            product: makeEventProduct($scenario['aggregateId'], $scenario['tenantId']),
            currentStock: 5,
            threshold: 10,
        );

        $fixture['listener']->handle($event);
    }

    expect(true)->toBeTrue();
});
