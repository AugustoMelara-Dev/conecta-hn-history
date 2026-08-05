<?php

declare(strict_types=1);

namespace App\Application\Ai\Evaluation;

use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Application\Ai\Security\AiContentCipher;
use App\Application\Ai\Security\EncryptedAiContent;
use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final class AiEvaluationRunner
{
    private const ARTIFACT_PATTERN = '/^git:[a-f0-9]{16,64}$/';

    /** @var list<string> */
    private const CONFIG_KEYS = [
        'fixture_path', 'dataset_checksum', 'deterministic_seed', 'evaluator_version',
        'evaluator_checksum', 'thresholds', 'baseline_run_id', 'release_policy_id',
    ];

    /** @var list<string> */
    private const CASE_KEYS = [
        'id', 'surface', 'market', 'input', 'authorized_evidence', 'required_claims',
        'forbidden_claims', 'expected_schema', 'expected_tool_intent',
        'confirmation_required', 'max_latency_ms', 'max_cost_usd', 'observed',
    ];

    public function __construct(
        private readonly AiContentCipher $cipher,
        private readonly EvaluationRunEvidence $runEvidence,
    ) {}

    public function run(
        string $evaluationVersionId,
        AiRuntimeSelection $selection,
        string $artifactSha,
    ): AiEvaluationDecision {
        if (! Str::isUuid($evaluationVersionId)
            || preg_match(self::ARTIFACT_PATTERN, $artifactSha) !== 1) {
            $this->deny();
        }

        return DB::transaction(function () use ($evaluationVersionId, $selection, $artifactSha): AiEvaluationDecision {
            $graph = $this->loadGraph($evaluationVersionId, $selection);
            $tenantId = (string) $graph['evaluation']->tenant_id;
            $configuration = $this->configuration($graph['evaluation']->configuration);
            $fixture = $this->fixture($configuration);
            $datasetChecksum = hash_file('sha256', $fixture['path']);
            if (! is_string($datasetChecksum)
                || ! hash_equals($configuration['dataset_checksum'], $datasetChecksum)) {
                $this->deny();
            }

            $requestChecksum = EvaluationRequestEvidence::checksum([
                'tenant_id' => $tenantId,
                'evaluation_version_id' => $evaluationVersionId,
                'evaluation_version_checksum' => (string) $graph['evaluation']->checksum,
                'release_policy_id' => $configuration['release_policy_id'],
                'release_policy_checksum' => (string) $graph['policy']->checksum,
                'agent_version_id' => $selection->agentVersionId,
                'candidate_checksum' => (string) $graph['agent']->checksum,
                'prompt_version_id' => $selection->promptVersionId,
                'prompt_checksum' => (string) $graph['prompt']->checksum,
                'provider_version_id' => $selection->providerVersionId,
                'provider_checksum' => (string) $graph['provider']->checksum,
                'model_version_id' => $selection->modelVersionId,
                'model_checksum' => (string) $graph['model']->checksum,
                'dataset_checksum' => $datasetChecksum,
                'evaluator_version' => $configuration['evaluator_version'],
                'evaluator_checksum' => $configuration['evaluator_checksum'],
                'deterministic_seed' => $configuration['deterministic_seed'],
                'baseline_run_id' => $configuration['baseline_run_id'],
                'artifact_sha' => $artifactSha,
            ]);

            $existing = DB::table('ai_evaluation_runs')
                ->where('tenant_id', $tenantId)
                ->where('request_checksum', $requestChecksum)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                return $this->hydrateExisting($existing);
            }

            $cases = DB::table('ai_evaluation_cases')
                ->where('tenant_id', $tenantId)
                ->where('evaluation_version_id', $evaluationVersionId)
                ->lockForUpdate()
                ->get()
                ->keyBy(static fn (object $case): string => (string) $case->key);
            if ($cases->isEmpty() || $cases->count() !== count($fixture['cases'])) {
                $this->deny();
            }

            $runId = (string) Str::uuid();
            $startedAt = now();
            $caseResults = [];
            $resultRows = [];
            foreach ($fixture['cases'] as $fixtureCase) {
                $storedCase = $cases->get($fixtureCase['id']);
                if (! is_object($storedCase)) {
                    $this->deny();
                }
                $this->assertCaseBindings($storedCase, $fixtureCase);
                $resultId = (string) Str::uuid();
                $evaluated = $this->evaluateCase($storedCase, $fixtureCase);
                $encrypted = $this->cipher->encrypt(
                    $tenantId,
                    $resultId,
                    'evaluation_output',
                    $evaluated['output'],
                );
                $safeMetrics = $evaluated['safe_metrics'];
                $diagnostic = $evaluated['blocking_codes'][0] ?? null;
                $resultChecksum = EvaluationChecksum::hash([
                    'evaluation_case_id' => (string) $storedCase->id,
                    'status' => $evaluated['status'],
                    'input_checksum' => (string) $storedCase->input_hash,
                    'output_hash' => $encrypted->contentHash,
                    'metrics' => $safeMetrics,
                    'latency_ms' => $evaluated['latency_ms'],
                    'cost_usd' => number_format($evaluated['cost_usd'], 6, '.', ''),
                    'diagnostic_code' => $diagnostic,
                ]);
                $caseResults[] = new AiEvaluationCaseResult(
                    caseId: (string) $storedCase->id,
                    caseKey: $fixtureCase['id'],
                    status: $evaluated['status'],
                    inputChecksum: (string) $storedCase->input_hash,
                    resultChecksum: $resultChecksum,
                    latencyMs: $evaluated['latency_ms'],
                    costUsd: $evaluated['cost_usd'],
                    safeMetrics: $safeMetrics,
                    blockingCodes: $evaluated['blocking_codes'],
                    output: $evaluated['output'],
                );
                $resultRows[] = [
                    'id' => $resultId,
                    'tenant_id' => $tenantId,
                    'evaluation_run_id' => $runId,
                    'evaluation_case_id' => (string) $storedCase->id,
                    'status' => $evaluated['status'],
                    'score_minor' => $evaluated['status'] === 'passed' ? 100 : 0,
                    'output_ciphertext' => $encrypted->ciphertext,
                    'output_hash' => $encrypted->contentHash,
                    'encryption_key_version' => $encrypted->keyVersion,
                    'metrics' => json_encode($safeMetrics, JSON_THROW_ON_ERROR),
                    'failure_detail' => $diagnostic,
                    'completed_at' => $startedAt,
                    'input_checksum' => (string) $storedCase->input_hash,
                    'result_checksum' => $resultChecksum,
                    'latency_ms' => $evaluated['latency_ms'],
                    'cost_usd' => $evaluated['cost_usd'],
                    'diagnostic_code' => $diagnostic,
                    'created_at' => $startedAt,
                    'updated_at' => $startedAt,
                ];
            }

            $metrics = $this->metrics($caseResults, $configuration, $tenantId);
            $blockingCodes = $this->blockingCodes($caseResults, $metrics, $configuration['thresholds']);
            $status = $blockingCodes === [] ? 'passed' : 'failed';
            $orderedChecksums = collect($caseResults)
                ->sortBy('caseId')
                ->pluck('resultChecksum')
                ->values()
                ->all();
            $resultChecksum = EvaluationChecksum::hash([
                'case_result_checksums' => $orderedChecksums,
                'metrics' => $metrics->toArray(),
                'blocking_codes' => $blockingCodes,
            ]);

            DB::table('ai_evaluation_runs')->insert([
                'id' => $runId,
                'tenant_id' => $tenantId,
                'evaluation_version_id' => $evaluationVersionId,
                'agent_version_id' => $selection->agentVersionId,
                'prompt_version_id' => $selection->promptVersionId,
                'provider_version_id' => $selection->providerVersionId,
                'model_version_id' => $selection->modelVersionId,
                'status' => $status,
                'triggered_by' => 'deterministic_ci',
                'started_at' => $startedAt,
                'completed_at' => $startedAt,
                'release_policy_id' => $configuration['release_policy_id'],
                'baseline_run_id' => $configuration['baseline_run_id'],
                'release_policy_checksum' => (string) $graph['policy']->checksum,
                'evaluation_version_checksum' => (string) $graph['evaluation']->checksum,
                'candidate_checksum' => (string) $graph['agent']->checksum,
                'prompt_checksum' => (string) $graph['prompt']->checksum,
                'provider_checksum' => (string) $graph['provider']->checksum,
                'model_checksum' => (string) $graph['model']->checksum,
                'dataset_checksum' => $datasetChecksum,
                'evaluator_version' => $configuration['evaluator_version'],
                'evaluator_checksum' => $configuration['evaluator_checksum'],
                'artifact_sha' => $artifactSha,
                'request_checksum' => $requestChecksum,
                'result_checksum' => $resultChecksum,
                'deterministic_seed' => $configuration['deterministic_seed'],
                'aggregate_metrics' => json_encode($metrics->toArray(), JSON_THROW_ON_ERROR),
                'blocking_codes' => json_encode($blockingCodes, JSON_THROW_ON_ERROR),
                'blocking_failures' => count($blockingCodes),
                'created_at' => $startedAt,
                'updated_at' => $startedAt,
            ]);
            DB::table('ai_evaluation_results')->insert($resultRows);

            return new AiEvaluationDecision(
                runId: $runId,
                status: $status,
                metrics: $metrics,
                blockingFailures: $blockingCodes,
                resultChecksum: $resultChecksum,
            );
        }, 3);
    }

    /** @return array<string, object> */
    private function loadGraph(string $evaluationVersionId, AiRuntimeSelection $selection): array
    {
        $evaluation = DB::table('ai_evaluation_versions')->where('id', $evaluationVersionId)->lockForUpdate()->first();
        if ($evaluation === null || $evaluation->released_at === null
            || Carbon::parse((string) $evaluation->released_at)->isFuture()) {
            $this->deny();
        }
        $tenantId = (string) $evaluation->tenant_id;
        $suite = DB::table('ai_evaluation_suites')->where('tenant_id', $tenantId)
            ->where('id', $evaluation->evaluation_suite_id)->where('status', 'active')->lockForUpdate()->first();
        $agent = DB::table('ai_agent_versions')->where('tenant_id', $tenantId)
            ->where('id', $selection->agentVersionId)->lockForUpdate()->first();
        $prompt = DB::table('ai_prompt_versions')->where('tenant_id', $tenantId)
            ->where('id', $selection->promptVersionId)->lockForUpdate()->first();
        $provider = DB::table('ai_provider_versions')->where('id', $selection->providerVersionId)->lockForUpdate()->first();
        $model = DB::table('ai_model_versions')->where('id', $selection->modelVersionId)->lockForUpdate()->first();
        $providerDefinition = $provider === null ? null : DB::table('ai_providers')->where('id', $provider->provider_id)->where('status', 'active')->first();
        $modelDefinition = $model === null ? null : DB::table('ai_models')->where('id', $model->model_id)->where('status', 'active')->first();
        if ($suite === null || $agent === null || $prompt === null || $provider === null || $model === null
            || $providerDefinition === null || $modelDefinition === null
            || ! in_array((string) $agent->lifecycle_status, ['candidate', 'eligible', 'active'], true)
            || ! in_array((string) $prompt->lifecycle_status, ['candidate', 'eligible', 'active'], true)
            || ! in_array((string) $provider->lifecycle_status, ['candidate', 'eligible', 'active'], true)
            || ! in_array((string) $model->lifecycle_status, ['candidate', 'eligible', 'active'], true)
            || (bool) $agent->kill_switch_enabled || (bool) $prompt->kill_switch_enabled
            || (bool) $provider->kill_switch_enabled || (bool) $model->kill_switch_enabled
            || ! hash_equals((string) $agent->model_version_id, $selection->modelVersionId)
            || ! hash_equals((string) $model->provider_version_id, $selection->providerVersionId)
            || ! hash_equals((string) $modelDefinition->provider_id, (string) $provider->provider_id)
            || ! hash_equals((string) $providerDefinition->provider_key, $selection->providerKey)
            || ! hash_equals((string) $providerDefinition->adapter_key, $selection->adapterKey)
            || ! hash_equals((string) $provider->secret_reference, $selection->secretReference)
            || ! hash_equals((string) $modelDefinition->key, $selection->modelKey)) {
            $this->deny();
        }
        $agentConfiguration = $this->jsonObject($agent->configuration);
        $configuredCapabilities = $this->stringList($agentConfiguration['required_capabilities'] ?? null);
        $selectedCapabilities = $selection->requiredCapabilities;
        sort($configuredCapabilities);
        sort($selectedCapabilities);
        if (! hash_equals((string) ($agentConfiguration['prompt_version_id'] ?? ''), $selection->promptVersionId)
            || ! in_array($selection->modelVersionId, $this->stringList($agentConfiguration['model_version_ids'] ?? null), true)
            || ! hash_equals((string) ($agentConfiguration['output_schema'] ?? ''), $selection->outputSchema)
            || (int) ($agentConfiguration['max_output_tokens'] ?? 0) !== $selection->maxOutputTokens
            || $configuredCapabilities !== $selectedCapabilities) {
            $this->deny();
        }
        $storedPrompt = $this->cipher->decrypt(
            $tenantId,
            (string) $prompt->id,
            'prompt_template',
            new EncryptedAiContent(
                (string) $prompt->template_ciphertext,
                (string) $prompt->template_hash,
                (string) $prompt->encryption_key_version,
            ),
        );
        if (! hash_equals($storedPrompt, $selection->systemPrompt)) {
            $this->deny();
        }
        $configuration = $this->configuration($evaluation->configuration);
        $policy = DB::table('ai_release_policies')->where('tenant_id', $tenantId)
            ->where('id', $configuration['release_policy_id'])->where('status', 'active')->lockForUpdate()->first();
        if ($policy === null || ! $this->checksum((string) $policy->checksum)
            || ! $this->checksum((string) $evaluation->checksum)
            || ! $this->checksum((string) $agent->checksum)
            || ! $this->checksum((string) $prompt->checksum)
            || ! $this->checksum((string) $provider->checksum)
            || ! $this->checksum((string) $model->checksum)) {
            $this->deny();
        }

        return compact('evaluation', 'suite', 'policy', 'agent', 'prompt', 'provider', 'model');
    }

    /** @return array{fixture_path:string,dataset_checksum:string,deterministic_seed:int,evaluator_version:string,evaluator_checksum:string,thresholds:array<string,float|int>,baseline_run_id:?string,release_policy_id:string} */
    private function configuration(mixed $raw): array
    {
        $config = $this->jsonObject($raw);
        if (! $this->sameKeys($config, self::CONFIG_KEYS)) {
            $this->deny();
        }
        $fixturePath = $config['fixture_path'] ?? null;
        $datasetChecksum = $config['dataset_checksum'] ?? null;
        $seed = $config['deterministic_seed'] ?? null;
        $evaluatorVersion = $config['evaluator_version'] ?? null;
        $evaluatorChecksum = $config['evaluator_checksum'] ?? null;
        $thresholds = $config['thresholds'] ?? null;
        $baselineRunId = $config['baseline_run_id'] ?? null;
        $policyId = $config['release_policy_id'] ?? null;
        if (! is_string($fixturePath) || ! str_starts_with($fixturePath, 'tests/Fixtures/ai/')
            || str_contains(str_replace('\\', '/', $fixturePath), '../')
            || ! is_string($datasetChecksum) || ! $this->checksum($datasetChecksum)
            || ! is_int($seed) || $seed < 0
            || ! is_string($evaluatorVersion) || preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/', $evaluatorVersion) !== 1
            || ! is_string($evaluatorChecksum) || ! hash_equals(hash('sha256', $evaluatorVersion), $evaluatorChecksum)
            || ! is_array($thresholds) || array_is_list($thresholds)
            || ($baselineRunId !== null && (! is_string($baselineRunId) || ! Str::isUuid($baselineRunId)))
            || ! is_string($policyId) || ! Str::isUuid($policyId)) {
            $this->deny();
        }
        $this->validateThresholds($thresholds);

        return [
            'fixture_path' => $fixturePath, 'dataset_checksum' => $datasetChecksum,
            'deterministic_seed' => $seed, 'evaluator_version' => $evaluatorVersion,
            'evaluator_checksum' => $evaluatorChecksum, 'thresholds' => $thresholds,
            'baseline_run_id' => $baselineRunId, 'release_policy_id' => $policyId,
        ];
    }

    /** @return array{path:string,cases:list<array<string,mixed>>} */
    private function fixture(array $configuration): array
    {
        $root = realpath(base_path('tests/Fixtures/ai'));
        $path = realpath(base_path($configuration['fixture_path']));
        if ($root === false || $path === false
            || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)
            || ! is_file($path)) {
            $this->deny();
        }
        try {
            $decoded = json_decode((string) file_get_contents($path), true, 128, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->deny();
        }
        if (! is_array($decoded) || ! $this->sameKeys($decoded, ['dataset_version', 'cases'])
            || ! is_string($decoded['dataset_version']) || ! is_array($decoded['cases'])
            || ! array_is_list($decoded['cases']) || $decoded['cases'] === []) {
            $this->deny();
        }
        $ids = [];
        foreach ($decoded['cases'] as $case) {
            $this->validateFixtureCase($case);
            if (in_array($case['id'], $ids, true)) {
                $this->deny();
            }
            $ids[] = $case['id'];
        }

        return ['path' => $path, 'cases' => $decoded['cases']];
    }

    /** @param array<string, mixed> $case */
    private function validateFixtureCase(array $case): void
    {
        if (! $this->sameKeys($case, self::CASE_KEYS)
            || ! is_string($case['id']) || preg_match('/^[a-z0-9][a-z0-9.-]{2,127}$/', $case['id']) !== 1
            || ! in_array($case['surface'], ['public_discovery', 'tenant_private'], true)
            || ! is_array($case['market']) || ! $this->sameKeys($case['market'], ['country', 'locale', 'currency', 'timezone'])
            || ! is_string($case['market']['country']) || preg_match('/^[A-Z]{2}$/', $case['market']['country']) !== 1
            || ! is_string($case['market']['locale']) || ! is_string($case['market']['currency']) || ! is_string($case['market']['timezone'])
            || ! is_string($case['input']) || trim($case['input']) === ''
            || ! $this->stringArray($case['required_claims']) || ! $this->stringArray($case['forbidden_claims'])
            || ! is_string($case['expected_schema']) || ! is_string($case['expected_tool_intent'])
            || ! is_bool($case['confirmation_required'])
            || ! is_int($case['max_latency_ms']) || $case['max_latency_ms'] < 0
            || ! is_numeric($case['max_cost_usd']) || ! is_finite((float) $case['max_cost_usd']) || (float) $case['max_cost_usd'] < 0
            || ! is_array($case['authorized_evidence']) || ! array_is_list($case['authorized_evidence'])
            || ! is_array($case['observed']) || ! $this->sameKeys($case['observed'], [
                'schema_valid', 'retrieval_precision', 'grounded', 'citations_valid',
                'injection_resisted', 'tenant_safe', 'tool_intent', 'confirmation_enforced',
                'latency_ms', 'cost_usd', 'output',
            ])) {
            $this->deny();
        }
        foreach ($case['authorized_evidence'] as $evidence) {
            if (! is_array($evidence) || ! $this->sameKeys($evidence, ['id', 'currency', 'amount_minor'])
                || ! is_string($evidence['id']) || ! is_string($evidence['currency']) || ! is_int($evidence['amount_minor'])) {
                $this->deny();
            }
        }
        $observed = $case['observed'];
        foreach (['schema_valid', 'grounded', 'citations_valid', 'injection_resisted', 'tenant_safe', 'confirmation_enforced'] as $key) {
            if (! is_bool($observed[$key])) {
                $this->deny();
            }
        }
        if (! is_numeric($observed['retrieval_precision']) || ! is_finite((float) $observed['retrieval_precision'])
            || (float) $observed['retrieval_precision'] < 0 || (float) $observed['retrieval_precision'] > 1
            || ! is_string($observed['tool_intent']) || ! is_int($observed['latency_ms']) || $observed['latency_ms'] < 0
            || ! is_numeric($observed['cost_usd']) || ! is_finite((float) $observed['cost_usd']) || (float) $observed['cost_usd'] < 0
            || ! is_string($observed['output'])) {
            $this->deny();
        }
    }

    /** @param array<string, mixed> $fixtureCase */
    private function assertCaseBindings(object $stored, array $fixtureCase): void
    {
        $expected = json_encode([
            'authorized_evidence' => $fixtureCase['authorized_evidence'],
            'required_claims' => $fixtureCase['required_claims'],
            'forbidden_claims' => $fixtureCase['forbidden_claims'],
            'expected_schema' => $fixtureCase['expected_schema'],
            'expected_tool_intent' => $fixtureCase['expected_tool_intent'],
            'confirmation_required' => $fixtureCase['confirmation_required'],
            'max_latency_ms' => $fixtureCase['max_latency_ms'],
            'max_cost_usd' => $fixtureCase['max_cost_usd'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (! hash_equals((string) $stored->input_hash, hash('sha256', $fixtureCase['input']))
            || ! is_string($stored->expected_hash)
            || ! hash_equals($stored->expected_hash, hash('sha256', $expected))) {
            $this->deny();
        }
    }

    /** @param array<string, mixed> $fixtureCase @return array{status:string,safe_metrics:array<string,bool|float|int|string>,blocking_codes:list<string>,latency_ms:int,cost_usd:float,output:string} */
    private function evaluateCase(object $stored, array $fixtureCase): array
    {
        $observed = $fixtureCase['observed'];
        $checks = [
            'case_schema' => $observed['schema_valid'] === true,
            'case_grounding' => $observed['grounded'] === true,
            'case_citations' => $observed['citations_valid'] === true,
            'case_injection' => $observed['injection_resisted'] === true,
            'case_tenant_leakage' => $observed['tenant_safe'] === true,
            'case_tool_selection' => hash_equals($fixtureCase['expected_tool_intent'], $observed['tool_intent']),
            'case_confirmation' => ! $fixtureCase['confirmation_required'] || $observed['confirmation_enforced'] === true,
            'case_latency' => $observed['latency_ms'] <= $fixtureCase['max_latency_ms'],
            'case_cost' => (float) $observed['cost_usd'] <= (float) $fixtureCase['max_cost_usd'],
        ];
        foreach ($fixtureCase['required_claims'] as $claim) {
            $checks['case_required_claim'] = ($checks['case_required_claim'] ?? true)
                && str_contains($observed['output'], $claim);
        }
        foreach ($fixtureCase['forbidden_claims'] as $claim) {
            $checks['case_forbidden_claim'] = ($checks['case_forbidden_claim'] ?? true)
                && ! str_contains($observed['output'], $claim);
        }
        $blockingCodes = array_keys(array_filter($checks, static fn (bool $passed): bool => ! $passed));
        sort($blockingCodes);
        $safeMetrics = [
            'schema_valid' => $observed['schema_valid'],
            'retrieval_precision' => (float) $observed['retrieval_precision'],
            'grounded' => $observed['grounded'],
            'citations_valid' => $observed['citations_valid'],
            'injection_resisted' => $observed['injection_resisted'],
            'tenant_safe' => $observed['tenant_safe'],
            'tool_selected' => $checks['case_tool_selection'],
            'confirmation_enforced' => $checks['case_confirmation'],
        ];

        return [
            'status' => $blockingCodes === [] ? 'passed' : 'failed',
            'safe_metrics' => $safeMetrics,
            'blocking_codes' => $blockingCodes,
            'latency_ms' => $observed['latency_ms'],
            'cost_usd' => (float) $observed['cost_usd'],
            'output' => $observed['output'],
        ];
    }

    /** @param list<AiEvaluationCaseResult> $results */
    private function metrics(array $results, array $configuration, string $tenantId): EvaluationMetrics
    {
        $count = count($results);
        if ($count === 0) {
            $this->deny();
        }
        $average = static function (array $results, string $key) use ($count): float {
            return array_sum(array_map(static fn (AiEvaluationCaseResult $result): float => is_bool($result->safeMetrics[$key]) ? ($result->safeMetrics[$key] ? 1.0 : 0.0) : (float) $result->safeMetrics[$key], $results)) / $count;
        };
        $latencies = array_map(static fn (AiEvaluationCaseResult $result): int => $result->latencyMs, $results);
        sort($latencies);
        $p95 = $latencies[max(0, (int) ceil(0.95 * $count) - 1)];
        $meanCost = round(array_sum(array_map(static fn (AiEvaluationCaseResult $result): float => $result->costUsd, $results)) / $count, 6);
        $base = new EvaluationMetrics(
            $average($results, 'schema_valid'), $average($results, 'retrieval_precision'),
            $average($results, 'grounded'), $average($results, 'citations_valid'),
            $average($results, 'injection_resisted'), $average($results, 'tenant_safe'),
            $average($results, 'tool_selected'), $average($results, 'confirmation_enforced'),
            $p95, $meanCost,
        );
        if ($configuration['baseline_run_id'] === null) {
            return $base;
        }
        $baseline = DB::table('ai_evaluation_runs')->where('tenant_id', $tenantId)
            ->where('id', $configuration['baseline_run_id'])->lockForUpdate()->first();
        if ($baseline === null || ! in_array((string) $baseline->status, ['passed', 'failed'], true)
            || $baseline->completed_at === null || $baseline->aggregate_metrics === null) {
            $this->deny();
        }
        $prior = EvaluationMetrics::fromArray($this->jsonObject($baseline->aggregate_metrics));
        $current = $base->toArray();
        $previous = $prior->toArray();
        $deltas = [];
        foreach (array_keys($current) as $key) {
            if ($key !== 'baseline_deltas') {
                $deltas[$key] = round((float) $current[$key] - (float) $previous[$key], 6);
            }
        }

        return new EvaluationMetrics(
            schemaValidity: $base->schemaValidity,
            retrievalPrecision: $base->retrievalPrecision,
            groundedness: $base->groundedness,
            citationValidity: $base->citationValidity,
            injectionResistance: $base->injectionResistance,
            tenantLeakage: $base->tenantLeakage,
            toolSelection: $base->toolSelection,
            confirmationEnforcement: $base->confirmationEnforcement,
            p95LatencyMs: $base->p95LatencyMs,
            meanCostUsd: $base->meanCostUsd,
            baselineDeltas: $deltas,
        );
    }

    /** @param list<AiEvaluationCaseResult> $results @param array<string, float|int> $thresholds @return list<string> */
    private function blockingCodes(array $results, EvaluationMetrics $metrics, array $thresholds): array
    {
        $codes = [];
        foreach ($results as $result) {
            foreach ($result->blockingCodes as $code) {
                $codes[] = $code;
            }
        }
        $actual = $metrics->toArray();
        foreach (['schema_validity', 'retrieval_precision', 'groundedness', 'citation_validity', 'injection_resistance', 'tenant_leakage', 'tool_selection', 'confirmation_enforcement'] as $key) {
            if ((float) $actual[$key] < (float) $thresholds[$key]) {
                $codes[] = 'threshold_'.$key;
            }
        }
        if ($metrics->p95LatencyMs > (int) $thresholds['p95_latency_ms']) {
            $codes[] = 'threshold_p95_latency_ms';
        }
        if ($metrics->meanCostUsd > (float) $thresholds['mean_cost_usd']) {
            $codes[] = 'threshold_mean_cost_usd';
        }
        if ($metrics->baselineDeltas !== []) {
            if (isset($thresholds['max_rate_regression'])) {
                foreach (array_slice($metrics->baselineDeltas, 0, 8, true) as $delta) {
                    if ((float) $delta < -1 * (float) $thresholds['max_rate_regression']) {
                        $codes[] = 'regression_quality';
                    }
                }
            }
            if (isset($thresholds['max_latency_regression_ms'])
                && (float) $metrics->baselineDeltas['p95_latency_ms'] > (float) $thresholds['max_latency_regression_ms']) {
                $codes[] = 'regression_latency';
            }
            if (isset($thresholds['max_cost_regression_usd'])
                && (float) $metrics->baselineDeltas['mean_cost_usd'] > (float) $thresholds['max_cost_regression_usd']) {
                $codes[] = 'regression_cost';
            }
        }
        $codes = array_values(array_unique($codes));
        sort($codes);

        return $codes;
    }

    private function hydrateExisting(object $run): AiEvaluationDecision
    {
        if (! in_array((string) $run->status, ['passed', 'failed'], true)
            || ! is_string($run->result_checksum) || ! $this->checksum($run->result_checksum)
            || ! is_numeric($run->blocking_failures)) {
            $this->deny();
        }
        $metrics = EvaluationMetrics::fromArray($this->jsonObject($run->aggregate_metrics));
        $codes = $this->jsonList($run->blocking_codes);
        if ((int) $run->blocking_failures !== count($codes)) {
            $this->deny();
        }
        $integrity = $this->runEvidence->assert($run, true);
        if ($integrity['blocking_codes'] !== $codes) {
            $this->deny();
        }

        return new AiEvaluationDecision((string) $run->id, (string) $run->status, $metrics, $codes, (string) $run->result_checksum, true);
    }

    /** @param array<string, mixed> $thresholds */
    private function validateThresholds(array $thresholds): void
    {
        $required = ['schema_validity', 'retrieval_precision', 'groundedness', 'citation_validity', 'injection_resistance', 'tenant_leakage', 'tool_selection', 'confirmation_enforcement', 'p95_latency_ms', 'mean_cost_usd'];
        $optional = ['max_rate_regression', 'max_latency_regression_ms', 'max_cost_regression_usd'];
        if (array_diff($required, array_keys($thresholds)) !== [] || array_diff(array_keys($thresholds), [...$required, ...$optional]) !== []) {
            $this->deny();
        }
        foreach (array_slice($required, 0, 8) as $key) {
            if (! is_numeric($thresholds[$key]) || ! is_finite((float) $thresholds[$key]) || (float) $thresholds[$key] < 0 || (float) $thresholds[$key] > 1) {
                $this->deny();
            }
        }
        foreach (array_slice($required, 8) as $key) {
            if (! is_numeric($thresholds[$key]) || ! is_finite((float) $thresholds[$key]) || (float) $thresholds[$key] < 0) {
                $this->deny();
            }
        }
        foreach ($optional as $key) {
            if (isset($thresholds[$key]) && (! is_numeric($thresholds[$key]) || ! is_finite((float) $thresholds[$key]) || (float) $thresholds[$key] < 0)) {
                $this->deny();
            }
        }
    }

    /** @return array<string, mixed> */
    private function jsonObject(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->deny();
            }
        }
        if (! is_array($value) || array_is_list($value)) {
            $this->deny();
        }

        return $value;
    }

    /** @return list<string> */
    private function jsonList(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->deny();
            }
        }
        if (! is_array($value) || ! array_is_list($value) || ! $this->stringArray($value)) {
            $this->deny();
        }

        return $value;
    }

    private function stringArray(mixed $value): bool
    {
        return is_array($value) && array_is_list($value)
            && array_filter($value, static fn (mixed $item): bool => is_string($item) && trim($item) !== '') === $value;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return $this->stringArray($value) ? $value : [];
    }

    private function checksum(string $value): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    /** @param array<string, mixed> $value @param list<string> $keys */
    private function sameKeys(array $value, array $keys): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($keys);

        return $actual === $keys;
    }

    private function deny(): never
    {
        throw new AiExecutionDeniedException('evaluation_gate');
    }
}
