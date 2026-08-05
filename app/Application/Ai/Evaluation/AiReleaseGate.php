<?php

declare(strict_types=1);

namespace App\Application\Ai\Evaluation;

use App\Domain\Ai\Events\AiRegistryChanged;
use App\Domain\Shared\OutboxRepositoryInterface;
use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AiReleaseGate
{
    public function __construct(
        private readonly EvaluationRunEvidence $evidence,
        private readonly OutboxRepositoryInterface $outbox,
    ) {}

    public function decide(string $evaluationRunId, string $actorId): string
    {
        if (! Str::isUuid($evaluationRunId) || trim($actorId) === '' || mb_strlen($actorId) > 64) {
            $this->deny();
        }

        return DB::transaction(function () use ($evaluationRunId, $actorId): string {
            $run = DB::table('ai_evaluation_runs')->where('id', $evaluationRunId)->lockForUpdate()->first();
            if ($run === null || ! hash_equals('passed', (string) $run->status)
                || (int) $run->blocking_failures !== 0) {
                $this->deny();
            }
            $integrity = $this->evidence->assert($run, true);
            if ($integrity['blocking_codes'] !== [] || ! $integrity['all_passed']) {
                $this->deny();
            }
            $tenantId = (string) $run->tenant_id;
            $existing = DB::table('ai_release_decisions')->where('tenant_id', $tenantId)
                ->where('evaluation_run_id', $evaluationRunId)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->revoked_at !== null || ! hash_equals((string) $existing->run_result_checksum, (string) $run->result_checksum)) {
                    $this->deny();
                }

                return (string) $existing->id;
            }

            $evaluation = DB::table('ai_evaluation_versions')->where('tenant_id', $tenantId)
                ->where('id', $run->evaluation_version_id)->lockForUpdate()->first();
            $suite = $evaluation === null ? null : DB::table('ai_evaluation_suites')->where('tenant_id', $tenantId)
                ->where('id', $evaluation->evaluation_suite_id)->where('status', 'active')->lockForUpdate()->first();
            $policy = DB::table('ai_release_policies')->where('tenant_id', $tenantId)
                ->where('id', $run->release_policy_id)->where('status', 'active')->lockForUpdate()->first();
            $agent = DB::table('ai_agent_versions')->where('tenant_id', $tenantId)
                ->where('id', $run->agent_version_id)->lockForUpdate()->first();
            $prompt = DB::table('ai_prompt_versions')->where('tenant_id', $tenantId)
                ->where('id', $run->prompt_version_id)->lockForUpdate()->first();
            $provider = DB::table('ai_provider_versions')->where('id', $run->provider_version_id)->lockForUpdate()->first();
            $model = DB::table('ai_model_versions')->where('id', $run->model_version_id)->lockForUpdate()->first();
            if ($evaluation === null || $suite === null || $policy === null || $agent === null
                || $prompt === null || $provider === null || $model === null
                || $evaluation->released_at === null
                || Carbon::parse((string) $evaluation->released_at)->isFuture()
                || Carbon::parse((string) $evaluation->released_at)->greaterThan(Carbon::parse((string) $run->started_at))
                || ! in_array((string) $agent->lifecycle_status, ['candidate', 'eligible', 'active'], true)
                || ! in_array((string) $prompt->lifecycle_status, ['candidate', 'eligible', 'active'], true)
                || ! in_array((string) $provider->lifecycle_status, ['candidate', 'eligible', 'active'], true)
                || ! in_array((string) $model->lifecycle_status, ['candidate', 'eligible', 'active'], true)
                || (bool) $agent->kill_switch_enabled || (bool) $prompt->kill_switch_enabled
                || (bool) $provider->kill_switch_enabled || (bool) $model->kill_switch_enabled
                || ! $this->same($run->evaluation_version_checksum, $evaluation->checksum)
                || ! $this->same($run->release_policy_checksum, $policy->checksum)
                || ! $this->same($run->candidate_checksum, $agent->checksum)
                || ! $this->same($run->prompt_checksum, $prompt->checksum)
                || ! $this->same($run->provider_checksum, $provider->checksum)
                || ! $this->same($run->model_checksum, $model->checksum)
                || ! hash_equals((string) $agent->model_version_id, (string) $run->model_version_id)
                || ! hash_equals((string) $model->provider_version_id, (string) $run->provider_version_id)) {
                $this->deny();
            }
            $configuration = $this->object($evaluation->configuration);
            if (! $this->same($run->dataset_checksum, $configuration['dataset_checksum'] ?? null)
                || ! $this->same($run->evaluator_checksum, $configuration['evaluator_checksum'] ?? null)
                || ! hash_equals((string) $run->evaluator_version, (string) ($configuration['evaluator_version'] ?? ''))
                || ! hash_equals((string) $run->release_policy_id, (string) ($configuration['release_policy_id'] ?? ''))
                || (($configuration['baseline_run_id'] ?? null) !== null
                    && ! hash_equals((string) $run->baseline_run_id, (string) $configuration['baseline_run_id']))) {
                $this->deny();
            }
            $rules = $this->object($policy->rules);
            $validDays = (int) ($rules['valid_days'] ?? 30);
            if ($validDays < 1 || $validDays > 30) {
                $this->deny();
            }

            $decisionId = (string) Str::uuid();
            $decidedAt = now();
            DB::table('ai_release_decisions')->insert([
                'id' => $decisionId,
                'tenant_id' => $tenantId,
                'release_policy_id' => (string) $run->release_policy_id,
                'evaluation_run_id' => $evaluationRunId,
                'agent_version_id' => (string) $run->agent_version_id,
                'decision' => 'passed',
                'decided_by' => $actorId,
                'evidence_hash' => EvaluationDecisionEvidence::checksum($run),
                'rationale' => json_encode(['blocking_failures' => 0], JSON_THROW_ON_ERROR),
                'decided_at' => $decidedAt,
                'candidate_checksum' => (string) $run->candidate_checksum,
                'valid_from' => $decidedAt,
                'valid_until' => $decidedAt->copy()->addDays($validDays),
                'revoked_at' => null,
                'revoked_by' => null,
                'revocation_reason' => null,
                'prompt_version_id' => (string) $run->prompt_version_id,
                'provider_version_id' => (string) $run->provider_version_id,
                'model_version_id' => (string) $run->model_version_id,
                'release_policy_checksum' => (string) $run->release_policy_checksum,
                'evaluation_version_checksum' => (string) $run->evaluation_version_checksum,
                'prompt_checksum' => (string) $run->prompt_checksum,
                'provider_checksum' => (string) $run->provider_checksum,
                'model_checksum' => (string) $run->model_checksum,
                'dataset_checksum' => (string) $run->dataset_checksum,
                'evaluator_version' => (string) $run->evaluator_version,
                'evaluator_checksum' => (string) $run->evaluator_checksum,
                'artifact_sha' => (string) $run->artifact_sha,
                'run_result_checksum' => (string) $run->result_checksum,
                'created_at' => $decidedAt,
                'updated_at' => $decidedAt,
            ]);
            $this->outbox->append(new AiRegistryChanged(
                aggregateId: $decisionId,
                tenantId: $tenantId,
                actorId: $actorId,
                eventName: 'ai_registry.release_decision_created',
                reason: 'Deterministic evaluation release gate passed.',
                attributes: [
                    'evaluation_run_id' => $evaluationRunId,
                    'agent_version_id' => (string) $run->agent_version_id,
                    'blocking_failures' => 0,
                ],
                occurredAt: $decidedAt->toImmutable(),
            ));

            return $decisionId;
        }, 3);
    }

    /** @return array<string, mixed> */
    private function object(mixed $raw): array
    {
        if (is_string($raw)) {
            try {
                $raw = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $this->deny();
            }
        }
        if (! is_array($raw) || array_is_list($raw)) {
            $this->deny();
        }

        return $raw;
    }

    private function same(mixed $left, mixed $right): bool
    {
        return is_string($left) && is_string($right)
            && preg_match('/^[a-f0-9]{64}$/', $left) === 1
            && hash_equals($left, $right);
    }

    private function deny(): never
    {
        throw new AiExecutionDeniedException('evaluation_gate');
    }
}
