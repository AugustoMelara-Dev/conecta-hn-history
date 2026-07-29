<?php

declare(strict_types=1);

namespace Tests\Support\AI;

use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Models\City;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AiEligibleRegistryFixture
{
    public static function create(
        string $tenantId,
        string $capability,
        string $audience,
        string $outputSchema,
        int $maxOutputTokens = 600,
        string $marketCountryCode = 'HN',
    ): AiRuntimeSelection {
        if (DB::table('tenants')->where('id', $tenantId)->whereNull('city_id')->exists()) {
            $cityId = DB::table('cities')->value('id') ?? City::factory()->create()->getKey();
            DB::table('tenants')->where('id', $tenantId)->update(['city_id' => $cityId]);
        }

        $selection = AiRuntimeSelectionFixture::create(
            tenantId: $tenantId,
            systemPrompt: 'Registry-authorized test prompt.',
            outputSchema: $outputSchema,
            maxOutputTokens: $maxOutputTokens,
            marketCountryCode: $marketCountryCode,
        );
        DB::table('ai_agent_versions')->where('id', $selection->agentVersionId)->update([
            'configuration' => json_encode([
                'capability' => $capability,
                'audience' => $audience,
                'channels' => ['web'],
                'model_version_ids' => [$selection->modelVersionId],
                'prompt_version_id' => $selection->promptVersionId,
                'output_schema' => $outputSchema,
                'required_capabilities' => ['structured_output'],
                'max_output_tokens' => $maxOutputTokens,
            ], JSON_THROW_ON_ERROR),
        ]);

        $policyId = (string) Str::uuid();
        $decisionId = (string) Str::uuid();
        DB::table('ai_release_policies')->insert([
            'id' => $policyId,
            'tenant_id' => $tenantId,
            'key' => 'fixture.release.'.Str::uuid(),
            'name' => 'Fixture release policy',
            'checksum' => hash('sha256', $policyId),
            'rules' => json_encode([
                'allowed_market_country_codes' => [$marketCountryCode],
                'residency_country_codes' => [$marketCountryCode],
                'required_capabilities' => ['structured_output'],
            ], JSON_THROW_ON_ERROR),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_release_decisions')->insert([
            'id' => $decisionId,
            'tenant_id' => $tenantId,
            'release_policy_id' => $policyId,
            'evaluation_run_id' => null,
            'agent_version_id' => $selection->agentVersionId,
            'decision' => 'passed',
            'decided_by' => 'test-reviewer',
            'evidence_hash' => hash('sha256', $decisionId),
            'rationale' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
            'decided_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_agent_activations')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'scope_type' => 'tenant',
            'scope_key' => $tenantId,
            'agent_version_id' => $selection->agentVersionId,
            'rollout_percentage' => 100,
            'cohort_salt' => hash('sha256', $tenantId.'|'.$capability),
            'release_decision_id' => $decisionId,
            'previous_activation_id' => null,
            'effective_from' => now()->subSecond(),
            'effective_until' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new AiRuntimeSelection(
            providerVersionId: $selection->providerVersionId,
            modelVersionId: $selection->modelVersionId,
            agentVersionId: $selection->agentVersionId,
            promptVersionId: $selection->promptVersionId,
            providerKey: $selection->providerKey,
            adapterKey: $selection->adapterKey,
            secretReference: $selection->secretReference,
            modelKey: $selection->modelKey,
            systemPrompt: $selection->systemPrompt,
            outputSchema: $selection->outputSchema,
            requiredCapabilities: $selection->requiredCapabilities,
            maxOutputTokens: $selection->maxOutputTokens,
            releaseDecisionId: $decisionId,
        );
    }
}
