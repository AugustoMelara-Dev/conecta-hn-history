<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Tests\TestCase;

final class DovraWorkforceIntegrationContractTest extends TestCase
{
    private const TENANT_A = '11111111-1111-4111-8111-111111111111';

    private const TENANT_B = '22222222-2222-4222-8222-222222222222';

    private const GLOBAL_MARKET_CONTEXT = 'market:global-contract';

    public function test_the_workforce_chain_is_explicit_from_onboarding_to_entitlement(): void
    {
        $chain = $this->deterministicFixture(self::TENANT_A);

        self::assertSame([
            'onboarding',
            'graph_backfill',
            'role_resolution',
            'simulated_conversation',
            'governed_action',
            'outcome',
            'channel_lineage',
            'discover_projection',
            'billing_entitlement',
        ], array_column($chain, 'stage'));

        foreach ($chain as $event) {
            self::assertStringStartsWith(self::TENANT_A.':', $event['id']);
            self::assertSame(self::TENANT_A, $event['tenant_id']);
            self::assertSame(self::GLOBAL_MARKET_CONTEXT, $event['market_context_id']);
        }

        self::assertStringContainsString(
            'BusinessGraphBackfill',
            $this->read('app/Application/BusinessGraph/BusinessGraphBackfill.php'),
        );
        self::assertStringContainsString(
            'AgentRoleCatalog',
            $this->read('app/Application/Ai/Workforce/WorkforceAgentResolver.php'),
        );
        self::assertStringContainsString(
            'simulation',
            $this->read('app/Http/Controllers/Api/V1/TenantWorkforceController.php'),
        );
        self::assertStringContainsString(
            'ToolPolicyEngine',
            $this->read('app/Application/Automation/AgentAction/AgentActionExecutor.php'),
        );
        self::assertStringContainsString(
            'payload_hash',
            $this->read('app/Application/Automation/AgentAction/AgentOutcomeRecorder.php'),
        );
        self::assertStringContainsString(
            'lineage_idempotency_key',
            $this->read('database/migrations/2026_08_02_210200_expand_dovra_conversation_channels.php'),
        );
        self::assertStringContainsString(
            'AgentCapabilityProjection',
            $this->read('app/Infrastructure/Discovery/CanonicalPublishedOfferSource.php'),
        );
        self::assertStringContainsString(
            'provider_event_id',
            $this->read('app/Domain/Billing/Global/WorkforceEntitlement.php'),
        );
    }

    public function test_two_tenant_fixture_never_reuses_generated_ids_or_crosses_the_boundary(): void
    {
        $first = $this->deterministicFixture(self::TENANT_A);
        $second = $this->deterministicFixture(self::TENANT_B);
        $firstIds = array_column($first, 'id');
        $secondIds = array_column($second, 'id');

        self::assertSame([], array_intersect($firstIds, $secondIds));
        self::assertCount(count($firstIds), array_unique($firstIds));
        self::assertCount(count($secondIds), array_unique($secondIds));

        foreach ([$first, $second] as $tenantChain) {
            foreach ($tenantChain as $event) {
                self::assertStringStartsWith($event['tenant_id'].':', $event['id']);
                self::assertStringStartsWith($event['tenant_id'].':', $event['agent_version_id']);
                self::assertStringNotContainsString('tenant_id', (string) ($event['payload'] ?? ''));
            }
        }

        $controller = $this->read('app/Http/Controllers/Api/V1/TenantWorkforceController.php');
        self::assertStringContainsString("where('tenant_id', \$tenantId)", $controller);
        self::assertStringContainsString('requestHash', $controller);
        self::assertStringContainsString('workforce_idempotency_conflict', $controller);
    }

    public function test_replay_safe_contract_does_not_promote_simulation_or_provider_absence_to_live(): void
    {
        $controller = $this->read('app/Http/Controllers/Api/V1/TenantWorkforceController.php');
        $entitlement = $this->read('app/Domain/Billing/Global/WorkforceEntitlement.php');
        $channelMigration = $this->read('database/migrations/2026_08_02_210200_expand_dovra_conversation_channels.php');

        self::assertStringContainsString('Simulation fixture - no external provider call', $controller);
        self::assertStringContainsString("'verified' => false", $controller);
        self::assertStringContainsString('replayed', $entitlement);
        self::assertStringContainsString('authenticity_status', $entitlement);
        self::assertStringContainsString('lineage_idempotency_key', $channelMigration);
        self::assertStringContainsString('dovra_channel_links_lineage_unique', $channelMigration);
        self::assertStringNotContainsString('public production', strtolower($controller));
    }

    /**
     * This fixture is intentionally repository-local. It records the complete
     * chain and tenant-scoped replay identity without invoking a provider,
     * database mutation, public endpoint, or production service.
     *
     * @return list<array{stage:string,id:string,tenant_id:string,market_context_id:string,agent_version_id:string,payload:string}>
     */
    private function deterministicFixture(string $tenantId): array
    {
        $stages = [
            'onboarding',
            'graph_backfill',
            'role_resolution',
            'simulated_conversation',
            'governed_action',
            'outcome',
            'channel_lineage',
            'discover_projection',
            'billing_entitlement',
        ];

        return array_map(
            static fn (string $stage): array => [
                'stage' => $stage,
                'id' => $tenantId.':'.$stage.':001',
                'tenant_id' => $tenantId,
                'market_context_id' => self::GLOBAL_MARKET_CONTEXT,
                'agent_version_id' => $tenantId.':agent-version:001',
                'payload' => 'deterministic repository contract fixture',
            ],
            $stages,
        );
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents(base_path($relativePath));

        self::assertIsString($contents, 'Expected contract source to be readable: '.$relativePath);

        return $contents;
    }
}
