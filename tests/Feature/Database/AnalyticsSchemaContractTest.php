<?php

declare(strict_types=1);

use App\Models\TenantVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\DatabaseSchemaInspector;

uses(RefreshDatabase::class);

function analyticsColumnNames(string $table): array
{
    return DatabaseSchemaInspector::columnNames($table);
}

function analyticsIndexColumns(string $table, string $index): array
{
    return DatabaseSchemaInspector::indexColumns($table, $index);
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
        'signal_class',
        'source_authority',
        'is_verified',
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
    expect(analyticsIndexColumns('interactions', 'interactions_tenant_trust_class_created_idx'))
        ->toBe(['tenant_id', 'is_verified', 'signal_class', 'created_at']);
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

it('keeps verified commercial outcomes provenance and idempotency contract', function (): void {
    expect(Schema::hasTable('verified_commercial_outcomes'))->toBeTrue();

    expect(analyticsColumnNames('verified_commercial_outcomes'))->toContain(
        'tenant_id',
        'aggregate_type',
        'aggregate_id',
        'outcome',
        'projection_key',
        'amount_minor',
        'currency',
        'source_occurred_at',
        'source_version',
        'metadata'
    );

    expect(analyticsIndexColumns(
        'verified_commercial_outcomes',
        'verified_outcomes_tenant_type_occurred_idx',
    ))->toBe(['tenant_id', 'outcome', 'source_occurred_at']);
});
