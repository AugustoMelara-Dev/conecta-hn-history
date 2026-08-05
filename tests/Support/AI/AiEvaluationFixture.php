<?php

declare(strict_types=1);

namespace Tests\Support\AI;

use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Infrastructure\AI\Security\LaravelAiContentCipher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AiEvaluationFixture
{
    /** @param array<string, float|int> $thresholdOverrides @return array{evaluationVersionId:string,policyId:string} */
    public static function create(
        string $tenantId,
        AiRuntimeSelection $selection,
        array $thresholdOverrides = [],
    ): array {
        DB::table('ai_agent_versions')->where('id', $selection->agentVersionId)->update([
            'configuration' => json_encode([
                'prompt_version_id' => $selection->promptVersionId,
                'model_version_ids' => [$selection->modelVersionId],
                'output_schema' => $selection->outputSchema,
                'required_capabilities' => $selection->requiredCapabilities,
                'max_output_tokens' => $selection->maxOutputTokens,
            ], JSON_THROW_ON_ERROR),
        ]);
        $suiteId = (string) Str::uuid();
        $versionId = (string) Str::uuid();
        $policyId = (string) Str::uuid();
        $fixturePath = base_path('tests/Fixtures/ai/program05-launch-v1.json');
        $fixture = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
        $thresholds = array_merge([
            'schema_validity' => 1.0, 'retrieval_precision' => 1.0, 'groundedness' => 1.0,
            'citation_validity' => 1.0, 'injection_resistance' => 1.0, 'tenant_leakage' => 1.0,
            'tool_selection' => 1.0, 'confirmation_enforcement' => 1.0,
            'p95_latency_ms' => 500, 'mean_cost_usd' => 0.005,
        ], $thresholdOverrides);
        $evaluatorVersion = 'deterministic-v1';

        DB::table('ai_release_policies')->insert([
            'id' => $policyId, 'tenant_id' => $tenantId, 'key' => 'evaluation.'.Str::uuid(),
            'name' => 'Evaluation policy', 'checksum' => hash('sha256', $policyId),
            'rules' => json_encode(['valid_days' => 30], JSON_THROW_ON_ERROR), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ai_evaluation_suites')->insert([
            'id' => $suiteId, 'tenant_id' => $tenantId, 'key' => 'launch.'.Str::uuid(),
            'name' => 'Program 05 launch', 'visibility' => 'private', 'status' => 'active',
            'metadata' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ai_evaluation_versions')->insert([
            'id' => $versionId, 'tenant_id' => $tenantId, 'evaluation_suite_id' => $suiteId,
            'version' => 1, 'checksum' => hash('sha256', $versionId),
            'configuration' => json_encode([
                'fixture_path' => 'tests/Fixtures/ai/program05-launch-v1.json',
                'dataset_checksum' => hash_file('sha256', $fixturePath),
                'deterministic_seed' => 50210,
                'evaluator_version' => $evaluatorVersion,
                'evaluator_checksum' => hash('sha256', $evaluatorVersion),
                'thresholds' => $thresholds,
                'baseline_run_id' => null,
                'release_policy_id' => $policyId,
            ], JSON_THROW_ON_ERROR),
            'released_at' => now()->subSecond(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $cipher = app(LaravelAiContentCipher::class);
        foreach ($fixture['cases'] as $case) {
            $caseId = (string) Str::uuid();
            $expected = json_encode([
                'authorized_evidence' => $case['authorized_evidence'],
                'required_claims' => $case['required_claims'],
                'forbidden_claims' => $case['forbidden_claims'],
                'expected_schema' => $case['expected_schema'],
                'expected_tool_intent' => $case['expected_tool_intent'],
                'confirmation_required' => $case['confirmation_required'],
                'max_latency_ms' => $case['max_latency_ms'],
                'max_cost_usd' => $case['max_cost_usd'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $encryptedInput = $cipher->encrypt($tenantId, $caseId, 'evaluation_input', $case['input']);
            $encryptedExpected = $cipher->encrypt($tenantId, $caseId, 'evaluation_expected', $expected);
            DB::table('ai_evaluation_cases')->insert([
                'id' => $caseId, 'tenant_id' => $tenantId, 'evaluation_version_id' => $versionId,
                'key' => $case['id'], 'input_ciphertext' => $encryptedInput->ciphertext,
                'input_hash' => hash('sha256', $case['input']),
                'expected_ciphertext' => $encryptedExpected->ciphertext,
                'expected_hash' => hash('sha256', $expected),
                'encryption_key_version' => $encryptedInput->keyVersion,
                'criteria' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return ['evaluationVersionId' => $versionId, 'policyId' => $policyId];
    }
}
