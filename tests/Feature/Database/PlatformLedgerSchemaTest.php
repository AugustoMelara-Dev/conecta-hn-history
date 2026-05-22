<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function platformColumnNames(string $table): array
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

function platformIndexNames(string $table): array
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return collect(DB::select("PRAGMA index_list('{$table}')"))
            ->map(fn (object $index): string => (string) $index->name)
            ->all();
    }

    return collect(DB::select(
        "select distinct index_name from information_schema.statistics where table_schema = database() and table_name = ?",
        [$table]
    ))->map(fn (object $index): string => (string) $index->index_name)->all();
}

function platformPrimaryKeyColumns(string $table): array
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return collect(DB::select("PRAGMA table_info('{$table}')"))
            ->filter(fn (object $column): bool => (int) $column->pk > 0)
            ->sortBy(fn (object $column): int => (int) $column->pk)
            ->map(fn (object $column): string => (string) $column->name)
            ->values()
            ->all();
    }

    return collect(DB::select(
        "select column_name from information_schema.key_column_usage where table_schema = database() and table_name = ? and constraint_name = 'PRIMARY' order by ordinal_position",
        [$table]
    ))->map(fn (object $column): string => (string) $column->column_name)->all();
}

it('keeps outbox messages as the canonical worker ledger', function (): void {
    expect(Schema::hasTable('outbox_messages'))->toBeTrue();

    $columns = platformColumnNames('outbox_messages');

    expect($columns)->toContain(
        'id',
        'type',
        'payload',
        'status',
        'attempts',
        'locked_at',
        'aggregate_id',
        'partition_key',
        'sequence_number',
        'recovery_count',
        'quarantined_at',
        'worker_id',
        'published_at'
    );

    $indexes = platformIndexNames('outbox_messages');

    expect($indexes)->toContain(
        'idx_outbox_claim_optimization',
        'idx_outbox_partition_sequence',
        'idx_outbox_worker_status'
    );
});

it('keeps processed commands as the tenant scoped command idempotency ledger', function (): void {
    expect(Schema::hasTable('processed_commands'))->toBeTrue();

    $columns = platformColumnNames('processed_commands');

    expect($columns)->toContain(
        'idempotency_key',
        'tenant_id',
        'command_hash',
        'status',
        'response_payload',
        'created_at',
        'updated_at'
    );

    $indexes = platformIndexNames('processed_commands');

    expect(platformPrimaryKeyColumns('processed_commands'))->toBe(['idempotency_key']);
    expect($indexes)->toContain('processed_commands_tenant_id_index');
});

it('keeps stored events as the optimistic aggregate history ledger', function (): void {
    expect(Schema::hasTable('stored_events'))->toBeTrue();

    $columns = platformColumnNames('stored_events');

    expect($columns)->toContain(
        'id',
        'aggregate_uuid',
        'aggregate_type',
        'tenant_id',
        'event_type',
        'payload',
        'meta_data',
        'aggregate_version',
        'created_at'
    );

    $indexes = platformIndexNames('stored_events');

    expect($indexes)->toContain('stored_events_optimistic_lock_unique');
});

it('documents legacy platform ledger compatibility tables until forward cleanup', function (): void {
    expect(Schema::hasTable('domain_outbox'))->toBeTrue();
    expect(Schema::hasTable('domain_outbox_messages'))->toBeTrue();
    expect(Schema::hasTable('idempotency_keys'))->toBeTrue();
});
