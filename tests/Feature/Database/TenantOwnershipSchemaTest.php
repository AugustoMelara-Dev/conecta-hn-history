<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatabaseSchemaInspector;

uses(RefreshDatabase::class);

it('requires tenant ownership for orders at database level', function (): void {
    expect(DatabaseSchemaInspector::columnIsNullable('orders', 'tenant_id'))->toBeFalse();
});

it('keeps ai usage tenant ownership nullable by explicit domain decision', function (): void {
    expect(DatabaseSchemaInspector::columnIsNullable('ai_usage_logs', 'tenant_id'))->toBeTrue();
});

it('allows nullable tenant ownership only for documented cross-context tables', function (): void {
    $allowedNullableTenantTables = [
        'ai_executions',
        'ai_usage_logs',
        'stored_events',
        'users',
    ];

    $nullableTenantTables = DatabaseSchemaInspector::tablesWithColumn('tenant_id', true);

    sort($nullableTenantTables);
    sort($allowedNullableTenantTables);

    expect($nullableTenantTables)->toBe($allowedNullableTenantTables);
});

it('documents the current tenant owned table inventory', function (): void {
    $expectedTenantTables = [
        'ai_credit_grants',
        'ai_credit_ledger_entries',
        'ai_credit_reservations',
        'ai_credit_usage_alerts',
        'ai_executions',
        'ai_knowledge_chunks',
        'ai_tool_confirmations',
        'ai_usage_logs',
        'appointments',
        'availability_exceptions',
        'availability_rules',
        'availability_write_operations',
        'campaigns',
        'catalog_import_batches',
        'catalog_import_items',
        'coupons',
        'crm_deals',
        'crm_pipelines',
        'crm_stages',
        'customer_notes',
        'customer_profiles',
        'customer_tasks',
        'follows',
        'impersonation_grants',
        'interactions',
        'inventory_movements',
        'leads',
        'locations',
        'loyalty_ledger',
        'loyalty_programs',
        'loyalty_rewards',
        'menu_sections',
        'one_time_passwords',
        'opening_hours',
        'orders',
        'payments',
        'product_media_cleanup_tasks',
        'product_media_operations',
        'processed_commands',
        'products',
        'quotes',
        'reviews',
        'service_locations',
        'service_provider_locations',
        'services',
        'service_providers',
        'stored_events',
        'subscription_intents',
        'subscription_payments',
        'tenant_analytics',
        'tenant_billing_references',
        'tenant_favorites',
        'tenant_invitations',
        'tenant_markets',
        'tenant_memberships',
        'tenant_onboarding_milestone_events',
        'tenant_onboarding_states',
        'tenant_users',
        'tenant_visits',
        'users',
        'verified_commercial_outcomes',
    ];

    $actualTenantTables = DatabaseSchemaInspector::tablesWithColumn('tenant_id');

    sort($actualTenantTables);
    sort($expectedTenantTables);

    expect($actualTenantTables)->toBe($expectedTenantTables);
});
