<?php

declare(strict_types=1);

use App\Events\Inventory\LowStockThresholdReached;
use App\Domain\Shared\Services\TenantContext;
use App\Listeners\SendLowStockNotification;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    app(TenantContext::class)->setAsSystemRoot();
});

/**
 * @return array{tenantA: Tenant, tenantB: Tenant, productA: Product, listener: SendLowStockNotification}
 */
function createLowStockNotificationFixture(): array
{
    $tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
    $tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

    $productA = Product::factory()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Product A',
        'stock_quantity' => 5,
    ]);

    return [
        'tenantA' => $tenantA,
        'tenantB' => $tenantB,
        'productA' => $productA,
        'listener' => new SendLowStockNotification(),
    ];
}

test('processes product with correct tenant_id', function () {
    $fixture = createLowStockNotificationFixture();

    $event = new LowStockThresholdReached(
        product: $fixture['productA'],
        currentStock: 5,
        threshold: 10
    );

    $fixture['listener']->handle($event);

    expect(true)->toBeTrue();
});

test('blocks product from different tenant and logs security event', function () {
    $fixture = createLowStockNotificationFixture();
    $spoofedProduct = clone $fixture['productA'];
    $spoofedProduct->tenant_id = (string) $fixture['tenantB']->id;

    $event = new LowStockThresholdReached(
        product: $spoofedProduct,
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
            return str_contains($message, 'Cross-Tenant Access Attempt BLOCKED')
                && ($context['severity'] ?? null) === 'CRITICAL';
        });

    $fixture['listener']->handle($event);
});

test('gracefully returns for non-existent product', function () {
    $fixture = createLowStockNotificationFixture();
    $spoofedProduct = clone $fixture['productA'];
    $spoofedProduct->id = 99999999;

    $event = new LowStockThresholdReached(
        product: $spoofedProduct,
        currentStock: 5,
        threshold: 10
    );

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'not found'));

    $fixture['listener']->handle($event);

    expect(true)->toBeTrue();
});
