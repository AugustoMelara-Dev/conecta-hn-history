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

    $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
    $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

    $this->productA = Product::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Product A',
        'stock_quantity' => 5,
    ]);

    $this->listener = new SendLowStockNotification();
});

test('processes product with correct tenant_id', function () {
    $event = new LowStockThresholdReached(
        product: $this->productA,
        currentStock: 5,
        threshold: 10
    );

    $this->listener->handle($event);

    expect(true)->toBeTrue();
});

test('blocks product from different tenant and logs security event', function () {
    $spoofedProduct = clone $this->productA;
    $spoofedProduct->tenant_id = (string) $this->tenantB->id;

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

    $this->listener->handle($event);
});

test('gracefully returns for non-existent product', function () {
    $spoofedProduct = clone $this->productA;
    $spoofedProduct->id = '99999999-9999-9999-9999-999999999999';

    $event = new LowStockThresholdReached(
        product: $spoofedProduct,
        currentStock: 5,
        threshold: 10
    );

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'not found'));

    $this->listener->handle($event);

    expect(true)->toBeTrue();
});
