<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Ai\Registry\AiRegistryResolver;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Infrastructure\AI\Security\LaravelAiContentCipher;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AiRegistryResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_the_most_specific_exact_eligible_registry_selection(): void
    {
        $tenant = Tenant::factory()->create();
        $platform = $this->registryFixture($tenant, scopeType: 'platform', scopeKey: '*', modelKey: 'platform-model');
        $this->registryFixture($tenant, scopeType: 'tenant', scopeKey: (string) $tenant->id, modelKey: 'tenant-model');
        $specific = $this->registryFixture(
            $tenant,
            scopeType: 'tenant_channel',
            scopeKey: $tenant->id.'|web',
            modelKey: 'model-ca-1',
        );

        $selection = app(AiRegistryResolver::class)->resolve($this->registryQuery($tenant));

        self::assertSame($specific['provider_version_id'], $selection->providerVersionId);
        self::assertSame($specific['model_version_id'], $selection->modelVersionId);
        self::assertSame($specific['agent_version_id'], $selection->agentVersionId);
        self::assertSame($specific['prompt_version_id'], $selection->promptVersionId);
        self::assertSame($specific['release_decision_id'], $selection->releaseDecisionId);
        self::assertSame('fake.primary', $selection->secretReference);
        self::assertSame('fake_structured_text', $selection->adapterKey);
        self::assertSame('model-ca-1', $selection->modelKey);
        self::assertSame('Registry prompt.', $selection->systemPrompt);
        self::assertSame('private_agent.answer.v1', $selection->outputSchema);
        self::assertSame(['structured_output'], $selection->requiredCapabilities);
        self::assertSame(640, $selection->maxOutputTokens);
        self::assertNotSame($platform['agent_version_id'], $selection->agentVersionId);
    }

    public function test_it_rejects_conflicting_activations_at_the_same_specificity(): void
    {
        $tenant = Tenant::factory()->create();
        $this->registryFixture($tenant, scopeType: 'tenant', scopeKey: (string) $tenant->id);
        $this->registryFixture($tenant, scopeType: 'tenant', scopeKey: (string) $tenant->id);

        $this->expectResolutionDenied($this->registryQuery($tenant), 'registry_conflict');
    }

    public function test_rollout_zero_selects_the_previous_activation_and_one_hundred_selects_current(): void
    {
        $tenant = Tenant::factory()->create();
        $previous = $this->registryFixture(
            $tenant,
            scopeType: 'platform',
            scopeKey: '*',
            effectiveUntil: now()->subSecond(),
            modelKey: 'previous-model',
        );
        $current = $this->registryFixture(
            $tenant,
            scopeType: 'tenant',
            scopeKey: (string) $tenant->id,
            rolloutPercentage: 0,
            previousActivationId: $previous['activation_id'],
            modelKey: 'current-model',
        );

        $atZero = app(AiRegistryResolver::class)->resolve($this->registryQuery($tenant));
        self::assertSame($previous['agent_version_id'], $atZero->agentVersionId);

        DB::table('ai_agent_activations')->where('id', $current['activation_id'])->update([
            'rollout_percentage' => 100,
        ]);

        $atOneHundred = app(AiRegistryResolver::class)->resolve($this->registryQuery($tenant));
        self::assertSame($current['agent_version_id'], $atOneHundred->agentVersionId);
    }

    public function test_rollout_twenty_five_is_stable_for_each_cohort_subject(): void
    {
        $tenant = Tenant::factory()->create();
        $previous = $this->registryFixture(
            $tenant,
            scopeType: 'platform',
            scopeKey: '*',
            effectiveUntil: now()->subSecond(),
            modelKey: 'previous-model',
        );
        $current = $this->registryFixture(
            $tenant,
            scopeType: 'tenant',
            scopeKey: (string) $tenant->id,
            rolloutPercentage: 25,
            previousActivationId: $previous['activation_id'],
            cohortSalt: 'rollout-salt',
            modelKey: 'current-model',
        );

        $selected = app(AiRegistryResolver::class)->resolve($this->registryQuery($tenant, 'subject-a'));
        $notSelected = app(AiRegistryResolver::class)->resolve($this->registryQuery($tenant, 'subject-b'));
        $selectedAgain = app(AiRegistryResolver::class)->resolve($this->registryQuery($tenant, 'subject-a'));

        self::assertSame($current['agent_version_id'], $selected->agentVersionId);
        self::assertSame($previous['agent_version_id'], $notSelected->agentVersionId);
        self::assertSame($selected->agentVersionId, $selectedAgain->agentVersionId);
    }

    public function test_inactive_intervals_and_absent_tenant_context_are_unroutable(): void
    {
        $tenant = Tenant::factory()->create();
        $this->registryFixture(
            $tenant,
            scopeType: 'tenant',
            scopeKey: (string) $tenant->id,
            effectiveFrom: now()->addMinute(),
        );

        $this->expectResolutionDenied($this->registryQuery($tenant), 'registry_selection');
        $this->expectResolutionDenied(new AiRegistryQuery(
            tenantId: null,
            audience: 'private',
            capability: 'private_tenant_agent',
            channel: 'web',
            marketCountryCode: 'CA',
            actorId: (string) $tenant->id,
            cohortSubject: (string) $tenant->id,
        ), 'tenant_context');
    }

    public function test_draft_null_expired_and_revoked_release_state_is_unroutable(): void
    {
        foreach (['draft_provider', 'null_decision', 'inactive_policy', 'revoked_decision'] as $failure) {
            $tenant = Tenant::factory()->create();
            $fixture = $this->registryFixture(
                $tenant,
                scopeType: 'tenant',
                scopeKey: (string) $tenant->id,
                providerVersionStatus: $failure === 'draft_provider' ? 'draft' : 'active',
                releaseDecision: $failure === 'null_decision' ? null : 'passed',
                releasePolicyStatus: $failure === 'inactive_policy' ? 'retired' : 'active',
            );
            if ($failure === 'revoked_decision') {
                DB::table('ai_release_decisions')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => (string) $tenant->id,
                    'release_policy_id' => $fixture['release_policy_id'],
                    'evaluation_run_id' => null,
                    'agent_version_id' => $fixture['agent_version_id'],
                    'decision' => 'revoked',
                    'decided_by' => 'platform-reviewer',
                    'evidence_hash' => hash('sha256', 'revoked'),
                    'rationale' => json_encode(['reason' => 'regression'], JSON_THROW_ON_ERROR),
                    'decided_at' => now()->addSecond(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->expectResolutionDenied($this->registryQuery($tenant), 'registry_selection');
        }
    }

    public function test_blocked_provider_and_model_are_unroutable(): void
    {
        foreach (['provider', 'model'] as $blocked) {
            $tenant = Tenant::factory()->create();
            $this->registryFixture(
                $tenant,
                scopeType: 'tenant',
                scopeKey: (string) $tenant->id,
                providerStatus: $blocked === 'provider' ? 'blocked' : 'active',
                modelStatus: $blocked === 'model' ? 'blocked' : 'active',
            );

            $this->expectResolutionDenied($this->registryQuery($tenant), 'registry_selection');
        }
    }

    public function test_model_and_provider_version_must_share_the_exact_provider_lineage(): void
    {
        $tenant = Tenant::factory()->create();
        $fixture = $this->registryFixture(
            $tenant,
            scopeType: 'tenant',
            scopeKey: (string) $tenant->id,
        );
        $foreignProviderId = (string) Str::uuid();
        $foreignProviderVersionId = (string) Str::uuid();
        DB::table('ai_providers')->insert([
            'id' => $foreignProviderId,
            'provider_key' => 'foreign-'.Str::lower(Str::random(12)),
            'adapter_key' => 'foreign_structured_text',
            'default_secret_reference' => 'foreign.primary',
            'name' => 'Foreign provider',
            'status' => 'active',
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_provider_versions')->insert([
            'id' => $foreignProviderVersionId,
            'provider_id' => $foreignProviderId,
            'version' => 1,
            'checksum' => hash('sha256', $foreignProviderVersionId),
            'secret_reference' => 'foreign.primary',
            'status' => 'active',
            'configuration' => json_encode([
                'enabled' => true,
                'capabilities' => ['structured_output'],
                'allowed_market_country_codes' => ['CA'],
                'residency_country_codes' => ['CA'],
            ], JSON_THROW_ON_ERROR),
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_model_versions')->where('id', $fixture['model_version_id'])->update([
            'provider_version_id' => $foreignProviderVersionId,
        ]);

        $this->expectResolutionDenied($this->registryQuery($tenant), 'registry_selection');
    }

    public function test_capability_market_and_residency_mismatches_are_unroutable(): void
    {
        foreach ([
            'capability' => ['capabilities' => ['vision']],
            'market' => ['allowed_market_country_codes' => ['US']],
            'residency' => ['residency_country_codes' => ['US']],
        ] as $override) {
            $tenant = Tenant::factory()->create();
            $this->registryFixture(
                $tenant,
                scopeType: 'tenant',
                scopeKey: (string) $tenant->id,
                modelConfigurationOverride: $override,
            );

            $this->expectResolutionDenied($this->registryQuery($tenant), 'registry_selection');
        }
    }

    public function test_it_uses_only_an_explicit_eligible_model_fallback_from_the_agent_version(): void
    {
        $tenant = Tenant::factory()->create();
        $fixture = $this->registryFixture(
            $tenant,
            scopeType: 'tenant',
            scopeKey: (string) $tenant->id,
            modelKey: 'model-ca-2',
        );
        $blocked = $this->modelFixture(
            $fixture['provider_id'],
            $fixture['provider_version_id'],
            key: 'model-ca-blocked',
            status: 'blocked',
        );
        DB::table('ai_agent_versions')->where('id', $fixture['agent_version_id'])->update([
            'model_version_id' => $blocked['model_version_id'],
            'configuration' => json_encode([
                'capability' => 'private_tenant_agent',
                'audience' => 'private',
                'channels' => ['web'],
                'model_version_ids' => [$blocked['model_version_id'], $fixture['model_version_id']],
                'prompt_version_id' => $fixture['prompt_version_id'],
                'output_schema' => 'private_agent.answer.v1',
                'required_capabilities' => ['structured_output'],
                'max_output_tokens' => 640,
            ], JSON_THROW_ON_ERROR),
        ]);

        $selection = app(AiRegistryResolver::class)->resolve($this->registryQuery($tenant));

        self::assertSame($fixture['model_version_id'], $selection->modelVersionId);
        self::assertSame('model-ca-2', $selection->modelKey);
    }

    public function test_it_fails_closed_when_no_explicit_model_fallback_is_eligible(): void
    {
        $tenant = Tenant::factory()->create();
        $this->registryFixture(
            $tenant,
            scopeType: 'tenant',
            scopeKey: (string) $tenant->id,
            modelStatus: 'blocked',
        );

        $this->expectResolutionDenied($this->registryQuery($tenant), 'registry_selection');
    }

    /** @return array<string, string> */
    private function registryFixture(
        Tenant $tenant,
        string $scopeType,
        string $scopeKey,
        int $rolloutPercentage = 100,
        ?string $previousActivationId = null,
        string $cohortSalt = 'stable-salt',
        ?\DateTimeInterface $effectiveFrom = null,
        ?\DateTimeInterface $effectiveUntil = null,
        string $providerStatus = 'active',
        string $providerVersionStatus = 'active',
        string $modelStatus = 'active',
        ?string $releaseDecision = 'passed',
        string $releasePolicyStatus = 'active',
        string $modelKey = 'model-ca-1',
        array $modelConfigurationOverride = [],
    ): array {
        $tenantId = (string) $tenant->id;
        $providerId = (string) Str::uuid();
        $providerVersionId = (string) Str::uuid();
        $providerKey = 'fake-'.Str::lower(Str::random(12));
        DB::table('ai_providers')->insert([
            'id' => $providerId,
            'provider_key' => $providerKey,
            'adapter_key' => 'fake_structured_text',
            'default_secret_reference' => 'fake.primary',
            'name' => 'Fake',
            'status' => $providerStatus,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_provider_versions')->insert([
            'id' => $providerVersionId,
            'provider_id' => $providerId,
            'version' => 1,
            'checksum' => hash('sha256', $providerVersionId),
            'secret_reference' => 'fake.primary',
            'status' => $providerVersionStatus,
            'configuration' => json_encode([
                'enabled' => true,
                'capabilities' => ['structured_output'],
                'allowed_market_country_codes' => ['CA'],
                'residency_country_codes' => ['CA'],
            ], JSON_THROW_ON_ERROR),
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $model = $this->modelFixture(
            $providerId,
            $providerVersionId,
            $modelKey,
            $modelStatus,
            $modelConfigurationOverride,
        );

        $promptDefinitionId = (string) Str::uuid();
        $promptVersionId = (string) Str::uuid();
        DB::table('ai_prompt_definitions')->insert([
            'id' => $promptDefinitionId,
            'tenant_id' => $tenantId,
            'key' => 'private.answer.'.Str::uuid(),
            'name' => 'Private answer',
            'visibility' => 'tenant_private',
            'status' => 'active',
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $encrypted = app(LaravelAiContentCipher::class)->encrypt(
            $tenantId,
            $promptVersionId,
            'prompt_template',
            'Registry prompt.',
        );
        DB::table('ai_prompt_versions')->insert([
            'id' => $promptVersionId,
            'tenant_id' => $tenantId,
            'prompt_definition_id' => $promptDefinitionId,
            'version' => 1,
            'checksum' => hash('sha256', $promptVersionId),
            'template_ciphertext' => $encrypted->ciphertext,
            'template_hash' => $encrypted->contentHash,
            'encryption_key_version' => $encrypted->keyVersion,
            'input_schema' => json_encode([], JSON_THROW_ON_ERROR),
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $agentDefinitionId = (string) Str::uuid();
        $agentVersionId = (string) Str::uuid();
        DB::table('ai_agent_definitions')->insert([
            'id' => $agentDefinitionId,
            'tenant_id' => $tenantId,
            'key' => 'private.agent.'.Str::uuid(),
            'name' => 'Private agent',
            'visibility' => 'tenant_private',
            'status' => 'active',
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_agent_versions')->insert([
            'id' => $agentVersionId,
            'tenant_id' => $tenantId,
            'agent_definition_id' => $agentDefinitionId,
            'model_version_id' => $model['model_version_id'],
            'version' => 1,
            'checksum' => hash('sha256', $agentVersionId),
            'configuration' => json_encode([
                'capability' => 'private_tenant_agent',
                'audience' => 'private',
                'channels' => ['web'],
                'model_version_ids' => [$model['model_version_id']],
                'prompt_version_id' => $promptVersionId,
                'output_schema' => 'private_agent.answer.v1',
                'required_capabilities' => ['structured_output'],
                'max_output_tokens' => 640,
            ], JSON_THROW_ON_ERROR),
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $releasePolicyId = (string) Str::uuid();
        DB::table('ai_release_policies')->insert([
            'id' => $releasePolicyId,
            'tenant_id' => $tenantId,
            'key' => 'release.'.Str::uuid(),
            'name' => 'Release policy',
            'checksum' => hash('sha256', $releasePolicyId),
            'rules' => json_encode([
                'allowed_market_country_codes' => ['CA'],
                'residency_country_codes' => ['CA'],
                'required_capabilities' => ['structured_output'],
            ], JSON_THROW_ON_ERROR),
            'status' => $releasePolicyStatus,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $releaseDecisionId = $releaseDecision === null ? null : (string) Str::uuid();
        if ($releaseDecisionId !== null) {
            DB::table('ai_release_decisions')->insert([
                'id' => $releaseDecisionId,
                'tenant_id' => $tenantId,
                'release_policy_id' => $releasePolicyId,
                'evaluation_run_id' => null,
                'agent_version_id' => $agentVersionId,
                'decision' => $releaseDecision,
                'decided_by' => 'platform-reviewer',
                'evidence_hash' => hash('sha256', $releaseDecisionId),
                'rationale' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
                'decided_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $activationId = (string) Str::uuid();
        DB::table('ai_agent_activations')->insert([
            'id' => $activationId,
            'tenant_id' => $tenantId,
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'agent_version_id' => $agentVersionId,
            'rollout_percentage' => $rolloutPercentage,
            'cohort_salt' => $cohortSalt,
            'release_decision_id' => $releaseDecisionId,
            'previous_activation_id' => $previousActivationId,
            'effective_from' => $effectiveFrom ?? now()->subSecond(),
            'effective_until' => $effectiveUntil,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'provider_id' => $providerId,
            'provider_version_id' => $providerVersionId,
            'model_version_id' => $model['model_version_id'],
            'agent_version_id' => $agentVersionId,
            'prompt_version_id' => $promptVersionId,
            'release_policy_id' => $releasePolicyId,
            'release_decision_id' => (string) $releaseDecisionId,
            'activation_id' => $activationId,
        ];
    }

    /** @return array{model_version_id: string} */
    private function modelFixture(
        string $providerId,
        string $providerVersionId,
        string $key,
        string $status,
        array $configurationOverride = [],
    ): array {
        $modelId = (string) Str::uuid();
        $modelVersionId = (string) Str::uuid();
        DB::table('ai_models')->insert([
            'id' => $modelId,
            'provider_id' => $providerId,
            'key' => $key,
            'name' => $key,
            'status' => $status,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_model_versions')->insert([
            'id' => $modelVersionId,
            'model_id' => $modelId,
            'provider_version_id' => $providerVersionId,
            'version' => 1,
            'checksum' => hash('sha256', $modelVersionId),
            'context_window' => 16_000,
            'input_cost_minor' => 15,
            'output_cost_minor' => 60,
            'currency' => 'USD',
            'configuration' => json_encode(array_replace([
                'capabilities' => ['structured_output'],
                'allowed_market_country_codes' => ['CA'],
                'residency_country_codes' => ['CA'],
            ], $configurationOverride), JSON_THROW_ON_ERROR),
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['model_version_id' => $modelVersionId];
    }

    private function registryQuery(Tenant $tenant, ?string $cohortSubject = null): AiRegistryQuery
    {
        return new AiRegistryQuery(
            tenantId: (string) $tenant->id,
            audience: 'private',
            capability: 'private_tenant_agent',
            channel: 'web',
            marketCountryCode: 'CA',
            actorId: (string) $tenant->id,
            cohortSubject: $cohortSubject ?? (string) $tenant->id,
        );
    }

    private function expectResolutionDenied(AiRegistryQuery $query, string $reason): void
    {
        try {
            app(AiRegistryResolver::class)->resolve($query);
            self::fail("Expected registry resolution denial: {$reason}");
        } catch (AiExecutionDeniedException $exception) {
            self::assertSame($reason, $exception->reason);
        }
    }
}
