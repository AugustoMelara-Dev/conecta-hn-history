<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy;

final class PostgresRlsPolicyInventory
{
    /** @return list<string> */
    public static function standardTenantTables(): array
    {
        return [
            'products',
            'services',
            'service_providers',
            'orders',
            'appointments',
            'customer_profiles',
            'customer_notes',
            'customer_tasks',
            'crm_pipelines',
            'crm_stages',
            'crm_deals',
            'leads',
            'payments',
            'subscription_intents',
            'subscription_payments',
            'tenant_billing_references',
            'ai_executions',
            'ai_usage_logs',
            'ai_credit_grants',
            'ai_credit_ledger_entries',
            'ai_credit_reservations',
            'ai_credit_usage_alerts',
            'catalog_import_batches',
            'catalog_import_items',
            'campaigns',
            'interactions',
            'loyalty_ledger',
            'inventory_movements',
            'tenant_memberships',
            'tenant_invitations',
            'impersonation_grants',
            'tenant_markets',
            'locations',
            'service_locations',
            'service_provider_locations',
            'availability_rules',
            'availability_exceptions',
            'availability_write_operations',
            'quotes',
            'ai_agent_definitions',
            'ai_agent_versions',
            'ai_prompt_definitions',
            'ai_prompt_versions',
            'ai_release_policies',
            'ai_agent_activations',
            'ai_conversations',
            'ai_conversation_participants',
            'ai_messages',
            'ai_message_evidence',
            'ai_message_failures',
            'ai_handoffs',
            'ai_evaluation_suites',
            'ai_evaluation_versions',
            'ai_evaluation_cases',
            'ai_evaluation_runs',
            'ai_evaluation_results',
            'ai_release_decisions',
        ];
    }

    /** @return list<string> */
    public static function specialPolicyTables(): array
    {
        return [
            'ai_knowledge_chunks',
            'ai_tool_confirmations',
        ];
    }

    /** @return list<string> */
    public static function requiredTables(): array
    {
        return [
            ...self::standardTenantTables(),
            ...self::specialPolicyTables(),
        ];
    }
}
