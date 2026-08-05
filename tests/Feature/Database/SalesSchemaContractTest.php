<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\DatabaseSchemaInspector;

uses(RefreshDatabase::class);

function salesColumnNames(string $table): array
{
    return DatabaseSchemaInspector::columnNames($table);
}

function salesIndexColumns(string $table, string $index): array
{
    return DatabaseSchemaInspector::indexColumns($table, $index);
}

it('keeps orders as the tenant owned sales aggregate root', function (): void {
    expect(Schema::hasTable('orders'))->toBeTrue();

    $columns = salesColumnNames('orders');

    expect($columns)->toContain(
        'id',
        'tenant_id',
        'order_number',
        'tracking_uuid',
        'currency',
        'refund_idempotency_key',
        'refund_attempted_at',
        'customer_name',
        'customer_phone',
        'subtotal',
        'discount',
        'delivery_fee',
        'total',
        'payment_status',
        'status',
        'created_at',
        'deleted_at'
    );

    expect(salesIndexColumns('orders', 'orders_tenant_order_number_unique'))->toBe(['tenant_id', 'order_number']);
});

it('keeps order items as parent owned immutable line snapshots', function (): void {
    expect(Schema::hasTable('order_items'))->toBeTrue();

    $columns = salesColumnNames('order_items');

    expect($columns)->toContain(
        'id',
        'order_id',
        'product_id',
        'product_name',
        'product_price',
        'unit_price',
        'total_price',
        'quantity',
        'selected_options',
        'notes'
    );

    expect($columns)->not->toContain('tenant_id');
});

it('keeps order events as parent owned timeline entries', function (): void {
    expect(Schema::hasTable('order_events'))->toBeTrue();

    $columns = salesColumnNames('order_events');

    expect($columns)->toContain(
        'id',
        'order_id',
        'event_type',
        'payload',
        'actor_id',
        'occurred_at'
    );

    expect($columns)->not->toContain('tenant_id');
});
