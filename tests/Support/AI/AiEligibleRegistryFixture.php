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
            visibility: $audience === 'public' ? 'platform_public' : 'tenant_private',
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

        $evaluationSuiteId = (string) Str::uuid();
        $evaluationVersionId = (string) Str::uuid();
        $evaluationCaseId = (string) Str::uuid();
        $evaluationRunId = (string) Str::uuid();
        DB::table('ai_evaluation_suites')->insert([
            'id' => $evaluationSuiteId,
            'tenant_id' => $tenantId,
            'key' => 'fixture.evaluation.'.Str::uuid(),
            'name' => 'Fixture release evaluation',
            'visibility' => 'private',
            'status' => 'active',
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_evaluation_versions')->insert([
            'id' => $evaluationVersionId,
            'tenant_id' => $tenantId,
            'evaluation_suite_id' => $evaluationSuiteId,
            'version' => 1,
            'checksum' => hash('sha256', $evaluationVersionId),
            'configuration' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
            'released_at' => now()->subSecond(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_evaluation_cases')->insert([
            'id' => $evaluationCaseId,
            'tenant_id' => $tenantId,
            'evaluation_version_id' => $evaluationVersionId,
            'key' => 'fixture.release.case',
            'input_ciphertext' => 'fixture-ciphertext',
            'input_hash' => hash('sha256', 'fixture-release-input'),
            'expected_ciphertext' => null,
            'expected_hash' => null,
            'encryption_key_version' => 'fixture-v1',
            'criteria' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_evaluation_runs')->insert([
            'id' => $evaluationRunId,
            'tenant_id' => $tenantId,
            'evaluation_version_id' => $evaluationVersionId,
            'agent_version_id' => $selection->agentVersionId,
            'prompt_version_id' => $selection->promptVersionId,
            'provider_version_id' => $selection->providerVersionId,
            'model_version_id' => $selection->modelVersionId,
            'status' => 'completed',
            'triggered_by' => 'fixture',
            'started_at' => now()->subSeconds(2),
            'completed_at' => now()->subSecond(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_evaluation_results')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'evaluation_run_id' => $evaluationRunId,
            'evaluation_case_id' => $evaluationCaseId,
            'status' => 'passed',
            'score_minor' => 100,
            'output_ciphertext' => null,
            'output_hash' => null,
            'encryption_key_version' => null,
            'metrics' => null,
            'failure_detail' => null,
            'completed_at' => now()->subSecond(),
            'created_at' => now(),
            'updated_at' => now(),
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
            'evaluation_run_id' => $evaluationRunId,
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
