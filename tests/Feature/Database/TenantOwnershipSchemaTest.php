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
        'ai_assistant_runs',
        'ai_executions',
        'ai_usage_logs',
        'contact_tag',
        'deal_tag',
        'dovra_data_retention_records',
        'dovra_legal_holds',
        'dovra_privacy_request_events',
        'dovra_privacy_requests',
        'global_billing_lifecycle_events',
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
        'ai_agent_activations',
        'ai_agent_definitions',
        'ai_agent_versions',
        'ai_assistant_runs',
        'ai_conversation_participants',
        'ai_conversations',
        'ai_credit_grants',
        'ai_credit_ledger_entries',
        'ai_credit_reservations',
        'ai_credit_usage_alerts',
        'ai_evaluation_cases',
        'ai_evaluation_results',
        'ai_evaluation_runs',
        'ai_evaluation_suites',
        'ai_evaluation_versions',
        'ai_executions',
        'ai_handoffs',
        'ai_knowledge_chunks',
        'ai_message_evidence',
        'ai_message_failures',
        'ai_messages',
        'ai_prompt_definitions',
        'ai_prompt_versions',
        'ai_release_decisions',
        'ai_release_policies',
        'ai_tool_confirmations',
        'ai_usage_logs',
        'api_keys',
        'appointments',
        'automation_confirmation_attempts',
        'automation_integration_idempotency',
        'automation_tool_policies',
        'automation_webhook_deliveries',
        'automation_workflow_approvals',
        'automation_workflow_definitions',
        'automation_workflow_runs',
        'automation_workflow_step_attempts',
        'automation_workflow_steps',
        'automation_workflow_versions',
        'availability_exceptions',
        'availability_rules',
        'availability_write_operations',
        'campaign_deliveries',
        'campaigns',
        'catalog_import_batches',
        'catalog_import_items',
        'channel_capability_snapshots',
        'channel_connections',
        'channel_consents',
        'channel_delivery_attempts',
        'channel_delivery_outbox',
        'channel_identities',
        'channel_provider_health',
        'channel_webhook_claims',
        'contact_tag',
        'coupons',
        'crm_activities',
        'crm_companies',
        'crm_contacts',
        'crm_custom_fields',
        'crm_deals',
        'crm_pipelines',
        'crm_stages',
        'customer_notes',
        'customer_profiles',
        'customer_tasks',
        'deal_tag',
        'dovra_agent_action_audits',
        'dovra_agent_outcomes',
        'dovra_business_contexts',
        'dovra_business_edges',
        'dovra_business_facts',
        'dovra_conversation_channel_links',
        'dovra_data_retention_records',
        'dovra_legal_holds',
        'dovra_privacy_request_events',
        'dovra_privacy_requests',
        'dovra_workforce_activation_requests',
        'follows',
        'global_billing_credit_accounts',
        'global_billing_credit_alerts',
        'global_billing_credit_ledger_entries',
        'global_billing_credit_reconciliations',
        'global_billing_credit_reservations',
        'global_billing_entitlements',
        'global_billing_lifecycle_events',
        'impersonation_grants',
        'import_jobs',
        'interactions',
        'inventory_movements',
        'leads',
        'location_service_areas',
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
        'report_schedules',
        'reviews',
        'service_locations',
        'service_provider_locations',
        'services',
        'service_providers',
        'stored_events',
        'subscription_intents',
        'subscription_payments',
        'tags',
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
        'webhooks',
    ];

    $actualTenantTables = DatabaseSchemaInspector::tablesWithColumn('tenant_id');

    sort($actualTenantTables);
    sort($expectedTenantTables);

    expect($actualTenantTables)->toBe($expectedTenantTables);
});
