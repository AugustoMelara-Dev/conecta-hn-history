<?php

declare(strict_types=1);

use App\Application\Ai\Evaluation\AiEvaluationRunner;
use App\Application\Ai\Evaluation\AiReleaseGate;
use App\Application\Ai\Registry\AiReleaseEligibility;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AI\AiEvaluationFixture;
use Tests\Support\AI\AiRuntimeSelectionFixture;

uses(RefreshDatabase::class);

it('runs the versioned fixture deterministically and binds the release decision', function (): void {
    $tenant = Tenant::factory()->create();
    $selection = AiRuntimeSelectionFixture::create((string) $tenant->getKey());
    $fixture = AiEvaluationFixture::create((string) $tenant->getKey(), $selection);
    $versionId = $fixture['evaluationVersionId'];
    $policyId = $fixture['policyId'];

    $decision = app(AiEvaluationRunner::class)->run(
        $versionId,
        $selection,
        'git:0123456789abcdef',
    );

    expect($decision->status)->toBe('passed')
        ->and($decision->metrics->schemaValidity)->toBe(1.0)
        ->and($decision->metrics->tenantLeakage)->toBe(1.0)
        ->and($decision->blockingFailures)->toBe([]);
    $this->assertDatabaseHas('ai_evaluation_runs', [
        'id' => $decision->runId,
        'agent_version_id' => $selection->agentVersionId,
        'dataset_checksum' => hash_file('sha256', base_path('tests/Fixtures/ai/program05-launch-v1.json')),
        'blocking_failures' => 0,
    ]);
    expect(DB::table('ai_evaluation_results')->where('evaluation_run_id', $decision->runId)->count())->toBe(10)
        ->and(DB::table('ai_evaluation_results')->where('evaluation_run_id', $decision->runId)->whereNotNull('output_ciphertext')->count())->toBe(10)
        ->and(json_encode(DB::table('ai_evaluation_results')->where('evaluation_run_id', $decision->runId)->get()))->not->toContain('What did I sell today?');

    $releaseDecisionId = app(AiReleaseGate::class)->decide($decision->runId, (string) Str::uuid());
    $this->assertDatabaseHas('ai_release_decisions', [
        'id' => $releaseDecisionId,
        'release_policy_id' => $policyId,
        'evaluation_run_id' => $decision->runId,
        'artifact_sha' => 'git:0123456789abcdef',
        'run_result_checksum' => $decision->resultChecksum,
    ]);
});

it('reuses an exact deterministic request and blocks threshold failures', function (): void {
    $tenant = Tenant::factory()->create();
    $selection = AiRuntimeSelectionFixture::create((string) $tenant->getKey());
    $fixture = AiEvaluationFixture::create((string) $tenant->getKey(), $selection, ['p95_latency_ms' => 100]);
    $versionId = $fixture['evaluationVersionId'];

    $first = app(AiEvaluationRunner::class)->run($versionId, $selection, 'git:fedcba9876543210');
    $second = app(AiEvaluationRunner::class)->run($versionId, $selection, 'git:fedcba9876543210');

    expect($first->runId)->toBe($second->runId)
        ->and($first->status)->toBe('failed')
        ->and($first->blockingFailures)->toContain('threshold_p95_latency_ms');
    expect(fn () => app(AiReleaseGate::class)->decide($first->runId, (string) Str::uuid()))
        ->toThrow(AiExecutionDeniedException::class);
});

it('invalidates every exact release binding and deferred incomplete evidence shape', function (): void {
    $mutations = [
        'candidate' => static fn (array $state) => DB::table('ai_agent_versions')->where('id', $state['selection']->agentVersionId)->update(['checksum' => hash('sha256', 'mutated-candidate')]),
        'dataset' => static function (array $state): void {
            $configuration = json_decode((string) DB::table('ai_evaluation_versions')->where('id', $state['versionId'])->value('configuration'), true, 32, JSON_THROW_ON_ERROR);
            $configuration['dataset_checksum'] = hash('sha256', 'mutated-dataset');
            DB::table('ai_evaluation_versions')->where('id', $state['versionId'])->update(['configuration' => json_encode($configuration, JSON_THROW_ON_ERROR)]);
        },
        'evaluator' => static function (array $state): void {
            $configuration = json_decode((string) DB::table('ai_evaluation_versions')->where('id', $state['versionId'])->value('configuration'), true, 32, JSON_THROW_ON_ERROR);
            $configuration['evaluator_version'] = 'deterministic-v2';
            $configuration['evaluator_checksum'] = hash('sha256', 'deterministic-v2');
            DB::table('ai_evaluation_versions')->where('id', $state['versionId'])->update(['configuration' => json_encode($configuration, JSON_THROW_ON_ERROR)]);
        },
        'artifact' => static function (array $state): void {
            DB::table('ai_evaluation_runs')->where('id', $state['runId'])->update(['artifact_sha' => 'git:aaaaaaaaaaaaaaaa']);
            DB::table('ai_release_decisions')->where('evaluation_run_id', $state['runId'])->update(['artifact_sha' => 'git:aaaaaaaaaaaaaaaa']);
        },
        'run result' => static fn (array $state) => DB::table('ai_evaluation_results')->where('evaluation_run_id', $state['runId'])->limit(1)->update(['latency_ms' => 999]),
        'linked provider' => static fn (array $state) => DB::table('ai_provider_versions')->where('id', $state['selection']->providerVersionId)->update(['checksum' => hash('sha256', 'mutated-provider')]),
        'zero cases' => static function (array $state): void {
            DB::table('ai_evaluation_results')->where('evaluation_run_id', $state['runId'])->delete();
            DB::table('ai_evaluation_cases')->where('evaluation_version_id', $state['versionId'])->delete();
        },
        'missing result' => static function (array $state): void {
            $id = DB::table('ai_evaluation_results')->where('evaluation_run_id', $state['runId'])->value('id');
            DB::table('ai_evaluation_results')->where('id', $id)->delete();
        },
        'future completed at' => static fn (array $state) => DB::table('ai_evaluation_runs')->where('id', $state['runId'])->update(['completed_at' => now()->addHour()]),
        'prompt-only public visibility' => static function (array $state): void {
            $definitionId = DB::table('ai_prompt_versions')->where('id', $state['selection']->promptVersionId)->value('prompt_definition_id');
            DB::table('ai_prompt_definitions')->where('id', $definitionId)->update(['visibility' => 'tenant_private']);
        },
    ];

    foreach ($mutations as $name => $mutate) {
        $tenant = Tenant::factory()->create();
        $selection = AiRuntimeSelectionFixture::create((string) $tenant->getKey(), providerKey: 'fake-'.Str::lower(Str::random(6)));
        $fixture = AiEvaluationFixture::create((string) $tenant->getKey(), $selection);
        if ($name === 'prompt-only public visibility') {
            $agentConfig = json_decode((string) DB::table('ai_agent_versions')->where('id', $selection->agentVersionId)->value('configuration'), true, 32, JSON_THROW_ON_ERROR);
            $agentConfig['audience'] = 'public';
            DB::table('ai_agent_versions')->where('id', $selection->agentVersionId)->update(['configuration' => json_encode($agentConfig, JSON_THROW_ON_ERROR)]);
            $agentDefinitionId = DB::table('ai_agent_versions')->where('id', $selection->agentVersionId)->value('agent_definition_id');
            $promptDefinitionId = DB::table('ai_prompt_versions')->where('id', $selection->promptVersionId)->value('prompt_definition_id');
            DB::table('ai_agent_definitions')->where('id', $agentDefinitionId)->update(['visibility' => 'platform_public']);
            DB::table('ai_prompt_definitions')->where('id', $promptDefinitionId)->update(['visibility' => 'platform_public']);
        }
        $run = app(AiEvaluationRunner::class)->run($fixture['evaluationVersionId'], $selection, 'git:0123456789abcdef');
        $decisionId = app(AiReleaseGate::class)->decide($run->runId, 'test-actor');
        $state = ['selection' => $selection, 'versionId' => $fixture['evaluationVersionId'], 'runId' => $run->runId];
        $mutate($state);

        expect(fn () => app(AiReleaseEligibility::class)->assertExact(
            (string) $tenant->getKey(), $decisionId, ['candidate', 'eligible', 'active'], 'evaluation_gate',
        ))->toThrow(AiExecutionDeniedException::class);
    }
});

it('rejects traversal checksum disagreement duplicate ids and unknown fixture fields', function (): void {
    $tenant = Tenant::factory()->create();
    $selection = AiRuntimeSelectionFixture::create((string) $tenant->getKey());
    $fixture = AiEvaluationFixture::create((string) $tenant->getKey(), $selection);
    $baseConfiguration = json_decode((string) DB::table('ai_evaluation_versions')->where('id', $fixture['evaluationVersionId'])->value('configuration'), true, 32, JSON_THROW_ON_ERROR);

    foreach ([
        'checksum' => static function (array $configuration): array {
            $configuration['dataset_checksum'] = hash('sha256', 'wrong');

            return $configuration;
        },
        'traversal' => static function (array $configuration): array {
            $configuration['fixture_path'] = 'tests/Fixtures/ai/../../Pest.php';

            return $configuration;
        },
    ] as $mutate) {
        DB::table('ai_evaluation_versions')->where('id', $fixture['evaluationVersionId'])->update([
            'configuration' => json_encode($mutate($baseConfiguration), JSON_THROW_ON_ERROR),
        ]);
        expect(fn () => app(AiEvaluationRunner::class)->run(
            $fixture['evaluationVersionId'], $selection, 'git:0123456789abcdef',
        ))->toThrow(AiExecutionDeniedException::class);
    }

    $source = json_decode((string) file_get_contents(base_path('tests/Fixtures/ai/program05-launch-v1.json')), true, 64, JSON_THROW_ON_ERROR);
    $source['cases'][1]['id'] = $source['cases'][0]['id'];
    $source['cases'][0]['unexpected'] = true;
    $relative = 'tests/Fixtures/ai/task10-invalid-'.Str::lower(Str::random(12)).'.json';
    $path = base_path($relative);
    try {
        file_put_contents($path, json_encode($source, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $configuration = $baseConfiguration;
        $configuration['fixture_path'] = $relative;
        $configuration['dataset_checksum'] = hash_file('sha256', $path);
        DB::table('ai_evaluation_versions')->where('id', $fixture['evaluationVersionId'])->update([
            'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR),
        ]);
        expect(fn () => app(AiEvaluationRunner::class)->run(
            $fixture['evaluationVersionId'], $selection, 'git:0123456789abcdef',
        ))->toThrow(AiExecutionDeniedException::class);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});
