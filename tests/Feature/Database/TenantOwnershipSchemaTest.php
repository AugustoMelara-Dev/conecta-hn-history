<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('requires tenant ownership for orders at database level', function (): void {
    $driver = DB::connection()->getDriverName();

    if ($driver === 'sqlite') {
        $columns = DB::select("PRAGMA table_info('orders')");
        $tenantColumn = collect($columns)->firstWhere('name', 'tenant_id');

        expect($tenantColumn)->not->toBeNull();
        expect((int) $tenantColumn->notnull)->toBe(1);

        return;
    }

    $column = collect(DB::select(
        "select is_nullable from information_schema.columns where table_schema = database() and table_name = 'orders' and column_name = 'tenant_id'"
    ))->first();

    expect($column)->not->toBeNull();
    expect(strtoupper((string) $column->is_nullable))->toBe('NO');
});

it('keeps ai usage tenant ownership nullable by explicit domain decision', function (): void {
    $driver = DB::connection()->getDriverName();

    if ($driver === 'sqlite') {
        $columns = DB::select("PRAGMA table_info('ai_usage_logs')");
        $tenantColumn = collect($columns)->firstWhere('name', 'tenant_id');

        expect($tenantColumn)->not->toBeNull();
        expect((int) $tenantColumn->notnull)->toBe(0);

        return;
    }

    $column = collect(DB::select(
        "select is_nullable from information_schema.columns where table_schema = database() and table_name = 'ai_usage_logs' and column_name = 'tenant_id'"
    ))->first();

    expect($column)->not->toBeNull();
    expect(strtoupper((string) $column->is_nullable))->toBe('YES');
});

it('allows nullable tenant ownership only for documented cross-context tables', function (): void {
    $allowedNullableTenantTables = [
        'ai_usage_logs',
        'stored_events',
        'users',
    ];

    $driver = DB::connection()->getDriverName();
    $nullableTenantTables = [];

    if ($driver === 'sqlite') {
        $tables = DB::select("select name from sqlite_master where type = 'table'");

        foreach ($tables as $table) {
            $tableName = (string) $table->name;
            $escapedTableName = str_replace("'", "''", $tableName);
            $columns = DB::select("PRAGMA table_info('{$escapedTableName}')");
            $tenantColumn = collect($columns)->firstWhere('name', 'tenant_id');

            if ($tenantColumn !== null && (int) $tenantColumn->notnull === 0) {
                $nullableTenantTables[] = $tableName;
            }
        }
    } else {
        $columns = DB::select(
            "select table_name from information_schema.columns where table_schema = database() and column_name = 'tenant_id' and is_nullable = 'YES'"
        );

        $nullableTenantTables = collect($columns)
            ->map(fn (object $column): string => (string) $column->table_name)
            ->all();
    }

    sort($nullableTenantTables);
    sort($allowedNullableTenantTables);

    expect($nullableTenantTables)->toBe($allowedNullableTenantTables);
});

it('documents the current tenant owned table inventory', function (): void {
    $expectedTenantTables = [
        'ai_usage_logs',
        'appointments',
        'campaigns',
        'coupons',
        'crm_deals',
        'crm_pipelines',
        'crm_stages',
        'customer_notes',
        'customer_profiles',
        'customer_tasks',
        'follows',
        'interactions',
        'leads',
        'loyalty_ledger',
        'loyalty_programs',
        'loyalty_rewards',
        'menu_sections',
        'one_time_passwords',
        'opening_hours',
        'orders',
        'payments',
        'processed_commands',
        'products',
        'reviews',
        'services',
        'service_providers',
        'stored_events',
        'subscription_intents',
        'subscription_payments',
        'tenant_analytics',
        'tenant_billing_references',
        'tenant_memberships',
        'tenant_onboarding_milestone_events',
        'tenant_onboarding_states',
        'tenant_users',
        'tenant_visits',
        'users',
    ];

    $driver = DB::connection()->getDriverName();

    if ($driver === 'sqlite') {
        $tables = DB::select("select name from sqlite_master where type = 'table'");

        $actualTenantTables = collect($tables)
            ->map(fn (object $table): string => (string) $table->name)
            ->filter(function (string $tableName): bool {
                $escapedTableName = str_replace("'", "''", $tableName);
                $columns = DB::select("PRAGMA table_info('{$escapedTableName}')");

                return collect($columns)->contains(fn (object $column): bool => (string) $column->name === 'tenant_id');
            })
            ->values()
            ->all();
    } else {
        $columns = DB::select(
            "select table_name from information_schema.columns where table_schema = database() and column_name = 'tenant_id'"
        );

        $actualTenantTables = collect($columns)
            ->map(fn (object $column): string => (string) $column->table_name)
            ->all();
    }

    sort($actualTenantTables);
    sort($expectedTenantTables);

    expect($actualTenantTables)->toBe($expectedTenantTables);
});
