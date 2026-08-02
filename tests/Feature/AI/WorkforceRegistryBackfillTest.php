<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Registry\AiRegistryBackfill;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function test_role_metadata_creates_a_draft_without_rerouting_the_open_legacy_activation(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);

        app(AiRegistryBackfill::class)->run();

        $legacy = DB::table('ai_agent_versions')->first();
        self::assertNotNull($legacy);
        $activation = DB::table('ai_agent_activations')
            ->where('agent_version_id', $legacy->id)
            ->whereNull('effective_until')
            ->first();
        self::assertNotNull($activation);
        $historicalActivationId = (string) Str::uuid();
        DB::table('ai_agent_activations')->insert([
            'id' => $historicalActivationId,
            'tenant_id' => $activation->tenant_id,
            'scope_type' => $activation->scope_type,
            'scope_key' => $activation->scope_key,
            'agent_version_id' => $activation->agent_version_id,
            'rollout_percentage' => $activation->rollout_percentage,
            'cohort_salt' => $activation->cohort_salt,
            'release_decision_id' => null,
            'previous_activation_id' => null,
            'effective_from' => now()->subMinute(),
            'effective_until' => now()->subSecond(),
            'activated_by' => 'workforce_backfill_test',
            'reason' => 'Historical activation fixture',
            'lock_version' => 0,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subSecond(),
        ]);
        DB::table('ai_agent_activations')->where('id', $activation->id)->update([
            'previous_activation_id' => $historicalActivationId,
        ]);
        $activation = DB::table('ai_agent_activations')->where('id', $activation->id)->first();
        self::assertNotNull($activation);
        $activationBeforeRoleMetadata = [
            'id' => $activation->id,
            'agent_version_id' => $activation->agent_version_id,
            'previous_activation_id' => $activation->previous_activation_id,
            'effective_from' => $activation->effective_from,
            'effective_until' => $activation->effective_until,
        ];

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
        self::assertDatabaseHas('ai_agent_activations', $activationBeforeRoleMetadata);
        self::assertSame(1, DB::table('ai_agent_activations')->whereNull('effective_until')->count());
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
