<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Registry\AiRegistryBackfill;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\TestCase;

final class WorkforceRegistryBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_writes_catalog_role_metadata_and_remains_stable_on_repeat(): void
    {
        $platformTenant = Tenant::factory()->create();
        $privateTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), [(string) $privateTenant->getKey()]);

        $first = app(AiRegistryBackfill::class)->run();
        $second = app(AiRegistryBackfill::class)->run();

        self::assertSame($first->agentVersions, $second->agentVersions);
        self::assertSame(2, DB::table('ai_agent_versions')->count());

        $roles = DB::table('ai_agent_versions')
            ->join('ai_agent_definitions', 'ai_agent_definitions.id', '=', 'ai_agent_versions.agent_definition_id')
            ->orderBy('ai_agent_definitions.key')
            ->pluck('ai_agent_versions.configuration', 'ai_agent_definitions.key')
            ->map(fn (string $configuration): string => $this->decodeConfiguration($configuration)['workforce_role'] ?? '')
            ->all();

        self::assertSame([
            'public.discovery' => 'front_desk',
            'tenant.private_assistant' => 'manager',
        ], $roles);
    }

    public function test_role_metadata_creates_a_draft_without_mutating_the_legacy_active_version(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);

        app(AiRegistryBackfill::class)->run();

        $legacy = DB::table('ai_agent_versions')->first();
        self::assertNotNull($legacy);
        $legacyConfiguration = $this->decodeConfiguration((string) $legacy->configuration);
        unset($legacyConfiguration['workforce_role']);
        $legacyChecksum = hash('sha256', 'legacy-active-agent-without-workforce-role');
        DB::table('ai_agent_versions')->where('id', $legacy->id)->update([
            'configuration' => json_encode($legacyConfiguration, JSON_THROW_ON_ERROR),
            'checksum' => $legacyChecksum,
            'lifecycle_status' => 'active',
            'released_at' => now(),
        ]);

        app(AiRegistryBackfill::class)->run();

        self::assertSame(2, DB::table('ai_agent_versions')->count());
        self::assertDatabaseHas('ai_agent_versions', [
            'id' => $legacy->id,
            'checksum' => $legacyChecksum,
            'configuration' => json_encode($legacyConfiguration, JSON_THROW_ON_ERROR),
            'lifecycle_status' => 'active',
        ]);
        $replacement = DB::table('ai_agent_versions')->where('id', '!=', $legacy->id)->first();
        self::assertNotNull($replacement);
        self::assertSame('draft', $replacement->lifecycle_status);
        self::assertSame('front_desk', $this->decodeConfiguration((string) $replacement->configuration)['workforce_role'] ?? null);
    }

    public function test_unmapped_agent_key_is_recorded_as_a_gap_without_creating_registry_rows(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);
        $backfill = app(AiRegistryBackfill::class);
        $backfill->run();

        $method = (new ReflectionClass($backfill))->getMethod('backfillAgentFamily');
        $method->invoke(
            $backfill,
            (string) $platformTenant->getKey(),
            'private_tenant_agent',
            (array) config('ai.capabilities.private_tenant_agent'),
            'unmapped.workforce_agent',
            'tenant_private',
            'unmapped.workforce_agent.system',
            'Unmapped Workforce agent system prompt',
            'Fixture prompt',
        );

        $gaps = (new ReflectionClass($backfill))->getProperty('gaps');
        self::assertSame(1, $gaps->getValue($backfill));
        self::assertDatabaseMissing('ai_agent_definitions', ['key' => 'unmapped.workforce_agent']);
    }

    /** @return array<string, mixed> */
    private function decodeConfiguration(string $configuration): array
    {
        return json_decode($configuration, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param list<string> $allowlist */
    private function configureRegistry(string $platformTenantId, array $allowlist): void
    {
        config()->set('ai.public.billing_tenant_id', $platformTenantId);
        config()->set('ai.private.enabled', true);
        config()->set('ai.private.tenant_allowlist', $allowlist);
        config()->set('ai.providers', [
            'openai' => [
                'enabled' => true,
                'adapter_key' => 'openai_structured_text',
                'secret_reference' => 'openai.primary',
                'models' => [
                    'gpt-4o-mini' => [
                        'input_usd_per_million' => 0.15,
                        'output_usd_per_million' => 0.60,
                    ],
                ],
            ],
        ]);
        config()->set('ai.capabilities', [
            'public_discovery' => [
                'enabled' => true,
                'provider' => 'openai',
                'models' => ['gpt-4o-mini'],
                'tools' => ['public_catalog.search'],
            ],
            'private_tenant_agent' => [
                'enabled' => true,
                'provider' => 'openai',
                'models' => ['gpt-4o-mini'],
                'tools' => ['tenant_catalog.read', 'tenant_availability.read'],
            ],
        ]);
    }
}
