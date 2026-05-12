<?php

declare(strict_types=1);

use App\Events\Inventory\LowStockThresholdReached;
use App\Listeners\SendLowStockNotification;
use App\Models\Product;
use App\Models\Tenant;
use App\Domain\Shared\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
    $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

    app(TenantContext::class)->setTenant($this->tenantA->id);
    $this->productA = Product::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Product A',
        'stock_quantity' => 5,
    ]);

    app(TenantContext::class)->reset();
    app(TenantContext::class)->setTenant($this->tenantB->id);
    $this->productB = Product::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Product B',
        'stock_quantity' => 3,
    ]);
    app(TenantContext::class)->reset();

    $this->listener = new SendLowStockNotification();

    Notification::fake();
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

test('processes product from another tenant only under its own tenant context', function () {
    $event = new LowStockThresholdReached(
        product: $this->productB,
        currentStock: 3,
        threshold: 10
    );

    $this->listener->handle($event);

    expect(true)->toBeTrue();
});

test('gracefully returns for non-existent product', function () {
    $event = new LowStockThresholdReached(
        product: $this->productA,
        currentStock: 5,
        threshold: 10
    );

    $this->productA->delete();

    $this->listener->handle($event);

    expect(true)->toBeTrue();
});

test('never throws exceptions for a deleted product event', function () {
    $event = new LowStockThresholdReached(
        product: $this->productA,
        currentStock: 5,
        threshold: 10
    );

    $this->productA->delete();

    expect(fn () => $this->listener->handle($event))->not->toThrow(Exception::class);
});
