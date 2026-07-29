<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Infrastructure\Tenancy\PostgresRlsPolicyInventory;
use PHPUnit\Framework\TestCase;

final class PostgresRlsPolicyInventoryTest extends TestCase
{
    public function test_program_three_high_risk_tables_are_in_the_canonical_inventory_and_migration(): void
    {
        $required = [
            'tenant_memberships',
            'tenant_invitations',
            'impersonation_grants',
            'tenant_markets',
            'locations',
        ];
        $inventory = PostgresRlsPolicyInventory::standardTenantTables();
        $migration = file_get_contents(
            dirname(__DIR__, 3)
            .'/database/migrations/2026_07_28_000003_expand_postgres_rls_for_governance.php'
        );

        self::assertIsString($migration);
        foreach ($required as $table) {
            self::assertContains($table, $inventory);
            self::assertStringContainsString("'{$table}'", $migration);
        }

        self::assertStringContainsString('ENABLE ROW LEVEL SECURITY', $migration);
        self::assertStringContainsString('FORCE ROW LEVEL SECURITY', $migration);
        self::assertStringContainsString("current_setting('app.tenant_id', true)", $migration);
        self::assertSame(
            PostgresRlsPolicyInventory::requiredTables(),
            array_values(array_unique(PostgresRlsPolicyInventory::requiredTables())),
        );
    }

    public function test_program_four_operational_tables_are_in_the_canonical_inventory_and_migration(): void
    {
        $required = [
            'products',
            'services',
            'service_providers',
            'service_locations',
            'service_provider_locations',
            'availability_rules',
            'availability_exceptions',
            'quotes',
        ];
        $inventory = PostgresRlsPolicyInventory::standardTenantTables();
        $migration = file_get_contents(
            dirname(__DIR__, 3)
            .'/database/migrations/2026_07_28_000005_expand_postgres_rls_for_business_operations.php'
        );

        self::assertIsString($migration);
        foreach ($required as $table) {
            self::assertContains($table, $inventory);
            self::assertStringContainsString("'{$table}'", $migration);
        }

        self::assertStringContainsString('ENABLE ROW LEVEL SECURITY', $migration);
        self::assertStringContainsString('FORCE ROW LEVEL SECURITY', $migration);
        self::assertStringContainsString("current_setting('app.tenant_id', true)", $migration);
        self::assertSame(
            PostgresRlsPolicyInventory::requiredTables(),
            array_values(array_unique(PostgresRlsPolicyInventory::requiredTables())),
        );
    }

    public function test_program_five_ai_foundation_tables_are_in_the_canonical_inventory(): void
    {
        $required = [
            'ai_agent_definitions',
            'ai_agent_versions',
            'ai_prompt_definitions',
            'ai_prompt_versions',
            'ai_conversations',
            'ai_conversation_participants',
            'ai_messages',
            'ai_message_evidence',
            'ai_message_failures',
            'ai_handoffs',
            'ai_agent_activations',
            'ai_evaluation_suites',
            'ai_evaluation_versions',
            'ai_evaluation_cases',
            'ai_evaluation_runs',
            'ai_evaluation_results',
            'ai_release_decisions',
        ];

        foreach ($required as $table) {
            self::assertContains($table, PostgresRlsPolicyInventory::standardTenantTables());
        }
    }
}
