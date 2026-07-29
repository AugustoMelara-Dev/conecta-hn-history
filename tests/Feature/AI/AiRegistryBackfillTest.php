<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Registry\AiRegistryBackfill;
use App\Application\Ai\Security\EncryptedAiContent;
use App\Infrastructure\AI\Security\LaravelAiContentCipher;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AiRegistryBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_backfills_idempotent_versioned_provider_model_agent_and_prompt_registries(): void
    {
        $platformTenant = Tenant::factory()->create();
        $allowlistedTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), [(string) $allowlistedTenant->getKey()]);
        putenv('OPENAI_API_KEY=resolved-openai-api-key-value');

        $first = app(AiRegistryBackfill::class)->run();
        $second = app(AiRegistryBackfill::class)->run();

        self::assertSame($first->providerVersions, $second->providerVersions);
        self::assertSame($first->promptVersions, $second->promptVersions);
        self::assertDatabaseHas('ai_providers', [
            'provider_key' => 'openai',
            'adapter_key' => 'openai_structured_text',
            'default_secret_reference' => 'openai.primary',
        ]);
        self::assertDatabaseHas('ai_agent_definitions', [
            'key' => 'public.discovery',
            'visibility' => 'platform_public',
            'tenant_id' => (string) $platformTenant->getKey(),
        ]);
        self::assertDatabaseHas('ai_agent_definitions', [
            'key' => 'tenant.private_assistant',
            'visibility' => 'tenant_private',
            'tenant_id' => (string) $allowlistedTenant->getKey(),
        ]);
        self::assertDatabaseMissing('ai_provider_versions', [
            'secret_reference' => 'resolved-openai-api-key-value',
        ]);

        $stored = collect([
            'ai_providers', 'ai_provider_versions', 'ai_models', 'ai_model_versions',
            'ai_agent_definitions', 'ai_agent_versions', 'ai_prompt_definitions', 'ai_prompt_versions',
        ])->flatMap(static fn (string $table) => DB::table($table)->get())
            ->map(static fn (object $row): string => json_encode($row, JSON_THROW_ON_ERROR));
        self::assertFalse($stored->contains(static fn (string $json): bool => str_contains($json, 'resolved-openai-api-key-value')));

        $prompt = DB::table('ai_prompt_versions')
            ->join('ai_prompt_definitions', 'ai_prompt_definitions.id', '=', 'ai_prompt_versions.prompt_definition_id')
            ->where('ai_prompt_definitions.key', 'public.discovery.system')
            ->select('ai_prompt_versions.*')
            ->first();
        self::assertNotNull($prompt);
        self::assertStringNotContainsString('You are Dovra AI public discovery', $prompt->template_ciphertext);
        self::assertStringStartsWith('You are Dovra AI public discovery', app(LaravelAiContentCipher::class)->decrypt(
            (string) $platformTenant->getKey(),
            $prompt->id,
            'prompt_template',
            new EncryptedAiContent($prompt->template_ciphertext, $prompt->template_hash, $prompt->encryption_key_version),
        ));
    }

    public function test_it_creates_an_immutable_model_version_when_configuration_changes(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);

        app(AiRegistryBackfill::class)->run();
        config()->set('ai.providers.openai.models.gpt-4o-mini.input_usd_per_million', 0.25);
        $report = app(AiRegistryBackfill::class)->run();

        self::assertSame(2, $report->modelVersions);
        self::assertSame(15, (int) DB::table('ai_model_versions')->orderBy('version')->value('input_cost_minor'));
        self::assertSame(25, (int) DB::table('ai_model_versions')->orderByDesc('version')->value('input_cost_minor'));
    }

    public function test_it_creates_a_provider_version_when_the_secret_reference_rotates(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);

        app(AiRegistryBackfill::class)->run();
        config()->set('ai.providers.openai.secret_reference', 'openai.rotated');
        $report = app(AiRegistryBackfill::class)->run();

        self::assertSame(2, $report->providerVersions);
        self::assertDatabaseHas('ai_provider_versions', ['secret_reference' => 'openai.primary', 'version' => 1]);
        self::assertDatabaseHas('ai_provider_versions', ['secret_reference' => 'openai.rotated', 'version' => 2]);
    }

    public function test_it_records_a_gap_when_any_capability_model_is_unknown(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);
        config()->set('ai.capabilities.public_discovery.models', ['gpt-4o-mini', 'unconfigured-fallback']);

        $report = app(AiRegistryBackfill::class)->run();

        self::assertGreaterThan(0, $report->gaps);
        self::assertDatabaseMissing('ai_agent_definitions', ['key' => 'public.discovery']);
    }

    public function test_it_versions_the_agent_when_a_secondary_capability_model_changes(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);
        config()->set('ai.providers.openai.models.gpt-4o', [
            'input_usd_per_million' => 2.50,
            'output_usd_per_million' => 10.00,
        ]);

        app(AiRegistryBackfill::class)->run();
        config()->set('ai.capabilities.public_discovery.models', ['gpt-4o-mini', 'gpt-4o']);
        $report = app(AiRegistryBackfill::class)->run();

        self::assertSame(2, $report->agentVersions);
        self::assertSame(['gpt-4o-mini', 'gpt-4o'], json_decode(
            DB::table('ai_agent_versions')->orderByDesc('version')->value('configuration'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        )['models']);
    }

    public function test_it_records_a_conflict_without_mutating_ambiguous_open_activations(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);
        app(AiRegistryBackfill::class)->run();

        $activation = DB::table('ai_agent_activations')->first();
        self::assertNotNull($activation);
        DB::table('ai_agent_activations')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $activation->tenant_id,
            'scope_type' => $activation->scope_type,
            'scope_key' => $activation->scope_key,
            'agent_version_id' => $activation->agent_version_id,
            'rollout_percentage' => $activation->rollout_percentage,
            'cohort_salt' => $activation->cohort_salt,
            'release_decision_id' => null,
            'previous_activation_id' => null,
            'effective_from' => now()->addSecond(),
            'effective_until' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = app(AiRegistryBackfill::class)->run();

        self::assertGreaterThan(0, $report->conflicts);
        self::assertSame(2, DB::table('ai_agent_activations')->whereNull('effective_until')->count());
    }

    public function test_it_creates_an_encrypted_prompt_version_when_the_key_version_changes(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);
        config()->set('ai.execution_retention.encryption_key_version', 'v1');

        app(AiRegistryBackfill::class)->run();
        config()->set('ai.execution_retention.encryption_key_version', 'v2');
        $report = app(AiRegistryBackfill::class)->run();

        self::assertSame(2, $report->promptVersions);
        self::assertSame('v2', DB::table('ai_prompt_versions')->orderByDesc('version')->value('encryption_key_version'));
    }

    public function test_it_records_gaps_for_missing_or_invalid_public_owners_and_unknown_models(): void
    {
        $this->configureRegistry(null, []);
        $missingOwner = app(AiRegistryBackfill::class)->run();

        self::assertGreaterThan(0, $missingOwner->gaps);
        self::assertDatabaseMissing('ai_agent_definitions', ['key' => 'public.discovery']);

        $this->configureRegistry('not-a-uuid', []);
        config()->set('ai.capabilities.public_discovery.models', ['unconfigured-model']);
        $invalidOwner = app(AiRegistryBackfill::class)->run();

        self::assertGreaterThan(0, $invalidOwner->gaps);
        self::assertDatabaseMissing('ai_agent_definitions', ['key' => 'public.discovery']);

        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);
        config()->set('ai.capabilities.public_discovery.models', ['unconfigured-model']);
        $unknownModel = app(AiRegistryBackfill::class)->run();

        self::assertGreaterThan(0, $unknownModel->gaps);
        self::assertDatabaseMissing('ai_agent_definitions', ['key' => 'public.discovery']);
    }

    public function test_it_records_conflicts_without_mutating_an_ambiguous_provider_definition(): void
    {
        $this->configureRegistry(null, []);
        DB::table('ai_providers')->insert([
            'id' => '00000000-0000-4000-8000-000000000001',
            'provider_key' => 'openai',
            'adapter_key' => 'different_adapter',
            'default_secret_reference' => 'different.reference',
            'name' => 'Conflicting OpenAI',
            'status' => 'active',
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = app(AiRegistryBackfill::class)->run();

        self::assertGreaterThan(0, $report->conflicts);
        self::assertSame('different_adapter', DB::table('ai_providers')->where('provider_key', 'openai')->value('adapter_key'));
    }

    public function test_prompt_content_is_encrypted_with_tenant_bound_associated_data(): void
    {
        $cipher = app(LaravelAiContentCipher::class);
        $content = $cipher->encrypt('00000000-0000-4000-8000-000000000010', '00000000-0000-4000-8000-000000000011', 'prompt_template', 'grounded prompt');

        self::assertNotSame('grounded prompt', $content->ciphertext);
        self::assertSame('grounded prompt', $cipher->decrypt(
            '00000000-0000-4000-8000-000000000010',
            '00000000-0000-4000-8000-000000000011',
            'prompt_template',
            $content,
        ));
        $this->expectException(\RuntimeException::class);
        $cipher->decrypt('00000000-0000-4000-8000-000000000012', '00000000-0000-4000-8000-000000000011', 'prompt_template', $content);
    }

    /** @param list<string> $allowlist */
    private function configureRegistry(?string $platformTenantId, array $allowlist): void
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
