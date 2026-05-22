<?php

declare(strict_types=1);

use App\Models\TenantVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function analyticsColumnNames(string $table): array
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

function analyticsIndexColumns(string $table, string $index): array
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        $matching = collect(DB::select("PRAGMA index_list('{$table}')"))
            ->first(fn (object $row): bool => (string) $row->name === $index);

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

it('keeps interactions aligned with the analytics model contract', function (): void {
    expect(Schema::hasTable('interactions'))->toBeTrue();

    $columns = analyticsColumnNames('interactions');

    expect($columns)->toContain(
        'id',
        'tenant_id',
        'visitor_id',
        'session_id',
        'product_id',
        'type',
        'metadata',
        'ip_address',
        'user_agent',
        'referrer',
        'url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'created_at'
    );

    expect(analyticsIndexColumns('interactions', 'interactions_tenant_session_type_created_idx'))
        ->toBe(['tenant_id', 'session_id', 'type', 'created_at']);
});

it('keeps tenant visits as hashed first party session fingerprints', function (): void {
    expect(Schema::hasTable('tenant_visits'))->toBeTrue();

    $columns = analyticsColumnNames('tenant_visits');

    expect($columns)->toContain(
        'id',
        'tenant_id',
        'ip_address',
        'user_agent',
        'session_hash',
        'referer',
        'device_type',
        'visited_at',
        'created_at'
    );

    $model = new TenantVisit;

    expect($model->getFillable())->toContain('session_hash', 'user_agent', 'referer', 'device_type');
    expect($model->getFillable())->not->toContain('ip_address', 'tenant_id');

    expect(analyticsIndexColumns('tenant_visits', 'idx_tenant_visits_lookup'))
        ->toBe(['tenant_id', 'session_hash']);
});

it('keeps tenant analytics as daily tenant aggregates', function (): void {
    expect(Schema::hasTable('tenant_analytics'))->toBeTrue();

    $columns = analyticsColumnNames('tenant_analytics');

    expect($columns)->toContain(
        'id',
        'tenant_id',
        'date',
        'views',
        'whatsapp_clicks',
        'unique_visitors',
        'created_at',
        'updated_at'
    );

    expect(analyticsIndexColumns('tenant_analytics', 'tenant_analytics_tenant_id_date_unique'))
        ->toBe(['tenant_id', 'date']);
});
