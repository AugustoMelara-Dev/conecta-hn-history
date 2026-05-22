<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function salesColumnNames(string $table): array
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return collect(DB::select("PRAGMA table_info('{$table}')"))
            ->map(fn (object $column): string => (string) $column->name)
            ->all();
    }

    return collect(DB::select(
        "select column_name from information_schema.columns where table_schema = database() and table_name = ? order by ordinal_position",
        [$table]
    ))->map(fn (object $column): string => (string) $column->column_name)->all();
}

function salesIndexColumns(string $table, string $index): array
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        $indexes = collect(DB::select("PRAGMA index_list('{$table}')"));
        $matching = $indexes->first(fn (object $row): bool => (string) $row->name === $index);

        if ($matching === null) {
            return [];
        }

        return collect(DB::select("PRAGMA index_info('{$index}')"))
            ->map(fn (object $column): string => (string) $column->name)
            ->all();
    }

    return collect(DB::select(
        "select column_name from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ? order by seq_in_index",
        [$table, $index]
    ))->map(fn (object $column): string => (string) $column->column_name)->all();
}

it('keeps orders as the tenant owned sales aggregate root', function (): void {
    expect(Schema::hasTable('orders'))->toBeTrue();

    $columns = salesColumnNames('orders');

    expect($columns)->toContain(
        'id',
        'tenant_id',
        'order_number',
        'tracking_uuid',
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
