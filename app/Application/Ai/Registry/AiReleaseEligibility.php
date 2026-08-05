<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use App\Application\Ai\Evaluation\EvaluationDecisionEvidence;
use App\Application\Ai\Evaluation\EvaluationRequestEvidence;
use App\Application\Ai\Evaluation\EvaluationRunEvidence;
use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AiReleaseEligibility
{
    public function __construct(private readonly EvaluationRunEvidence $runEvidence) {}

    /**
     * @param  list<string>  $allowedLifecycleStatuses
     */
    public function assertExact(
        string $tenantId,
        string $decisionId,
        array $allowedLifecycleStatuses,
        string $denialReason,
        ?string $expectedAgentVersionId = null,
        bool $lockForUpdate = false,
    ): AiReleaseCandidateSet {
        if (! Str::isUuid($tenantId)
            || ! Str::isUuid($decisionId)
            || $allowedLifecycleStatuses === []) {
            $this->deny($denialReason);
        }

        $decision = $this->read(DB::table('ai_release_decisions')
            ->where('tenant_id', $tenantId)
            ->where('id', $decisionId), $lockForUpdate)
            ->first();
        if ($decision === null
            || ! is_string($decision->agent_version_id)
            || ! Str::isUuid($decision->agent_version_id)
            || ! is_string($decision->evaluation_run_id)
            || ! Str::isUuid($decision->evaluation_run_id)
            || ($expectedAgentVersionId !== null
                && ! hash_equals($expectedAgentVersionId, (string) $decision->agent_version_id))
            || ! hash_equals('passed', (string) $decision->decision)
            || ! is_string($decision->candidate_checksum)
            || preg_match('/^[a-f0-9]{64}$/', $decision->candidate_checksum) !== 1
            || $decision->revoked_at !== null
            || ! $this->atOrBefore($decision->valid_from, now())
            || ($decision->valid_until !== null && ! $this->before(now(), $decision->valid_until))
            || ! $this->atOrBefore($decision->decided_at, now())) {
            $this->deny($denialReason);
        }

        $latest = $this->read(DB::table('ai_release_decisions')
            ->where('tenant_id', $tenantId)
            ->where('agent_version_id', $decision->agent_version_id)
            ->orderByDesc('decided_at')
            ->orderByDesc('id')
            ->limit(2), $lockForUpdate)
            ->get();
        if ($latest->isEmpty()
            || ! hash_equals($decisionId, (string) $latest->first()->id)
            || ($latest->count() > 1
                && hash_equals(
                    (string) $latest->first()->decided_at,
                    (string) $latest->get(1)->decided_at,
                ))) {
            $this->deny($denialReason);
        }

        $policy = $this->read(DB::table('ai_release_policies')
            ->where('tenant_id', $tenantId)
            ->where('id', $decision->release_policy_id)
            ->where('status', 'active'), $lockForUpdate)
            ->first();
        $run = $this->read(DB::table('ai_evaluation_runs')
            ->where('tenant_id', $tenantId)
            ->where('id', $decision->evaluation_run_id)
            ->whereIn('status', ['completed', 'passed']), $lockForUpdate)
            ->first();
        if ($policy === null
            || $run === null
            || ! is_string($run->evaluation_version_id)
            || ! Str::isUuid($run->evaluation_version_id)
            || ! $this->atOrBefore($run->started_at, $run->completed_at)
            || ! $this->atOrBefore($run->completed_at, $decision->decided_at)
            || ! $this->atOrBefore($run->completed_at, now())) {
            $this->deny($denialReason);
        }

        $evaluationVersion = $this->read(DB::table('ai_evaluation_versions')
            ->where('tenant_id', $tenantId)
            ->where('id', $run->evaluation_version_id), $lockForUpdate)
            ->first();
        $evaluationSuite = $evaluationVersion === null
            ? null
            : $this->read(DB::table('ai_evaluation_suites')
                ->where('tenant_id', $tenantId)
                ->where('id', $evaluationVersion->evaluation_suite_id)
                ->where('status', 'active'), $lockForUpdate)
                ->first();
        if ($evaluationVersion === null
            || $evaluationVersion->released_at === null
            || ! $this->atOrBefore($evaluationVersion->released_at, $decision->decided_at)
            || $evaluationSuite === null) {
            $this->deny($denialReason);
        }

        $agent = $this->read(DB::table('ai_agent_versions')
            ->where('tenant_id', $tenantId)
            ->where('id', $decision->agent_version_id), $lockForUpdate)
            ->first();
        if ($agent === null
            || ! in_array((string) $agent->lifecycle_status, $allowedLifecycleStatuses, true)
            || (bool) $agent->kill_switch_enabled
            || ! hash_equals((string) $agent->checksum, (string) $decision->candidate_checksum)) {
            $this->deny($denialReason);
        }
        $agentDefinition = $this->read(DB::table('ai_agent_definitions')
            ->where('tenant_id', $tenantId)
            ->where('id', $agent->agent_definition_id)
            ->where('status', 'active'), $lockForUpdate)
            ->first();
        $agentConfiguration = $this->jsonObject($agent->configuration);
        $promptVersionId = $agentConfiguration['prompt_version_id'] ?? null;
        $modelVersionIds = $this->stringList($agentConfiguration['model_version_ids'] ?? null);
        if ($agentDefinition === null
            || ! is_string($promptVersionId)
            || ! Str::isUuid($promptVersionId)
            || $modelVersionIds === []
            || ! in_array((string) $run->model_version_id, $modelVersionIds, true)
            || ! hash_equals((string) $run->agent_version_id, (string) $agent->id)
            || ! hash_equals((string) $run->prompt_version_id, $promptVersionId)) {
            $this->deny($denialReason);
        }

        $prompt = $this->read(DB::table('ai_prompt_versions')
            ->where('tenant_id', $tenantId)
            ->where('id', $run->prompt_version_id), $lockForUpdate)
            ->first();
        $promptDefinition = $prompt === null
            ? null
            : $this->read(DB::table('ai_prompt_definitions')
                ->where('tenant_id', $tenantId)
                ->where('id', $prompt->prompt_definition_id)
                ->where('status', 'active'), $lockForUpdate)
                ->first();
        $model = $this->read(DB::table('ai_model_versions')
            ->where('id', $run->model_version_id), $lockForUpdate)
            ->first();
        $modelDefinition = $model === null
            ? null
            : $this->read(DB::table('ai_models')
                ->where('id', $model->model_id)
                ->where('status', 'active'), $lockForUpdate)
                ->first();
        $provider = $this->read(DB::table('ai_provider_versions')
            ->where('id', $run->provider_version_id), $lockForUpdate)
            ->first();
        $providerDefinition = $provider === null
            ? null
            : $this->read(DB::table('ai_providers')
                ->where('id', $provider->provider_id)
                ->where('status', 'active'), $lockForUpdate)
                ->first();
        foreach ([$prompt, $model, $provider] as $version) {
            if ($version === null
                || ! in_array((string) $version->lifecycle_status, $allowedLifecycleStatuses, true)
                || (bool) $version->kill_switch_enabled) {
                $this->deny($denialReason);
            }
        }
        if ($promptDefinition === null
            || $modelDefinition === null
            || $providerDefinition === null
            || ! hash_equals('active', (string) $provider->status)
            || ! hash_equals((string) $model->provider_version_id, (string) $provider->id)
            || ! hash_equals((string) $modelDefinition->provider_id, (string) $provider->provider_id)
            || ! hash_equals((string) $run->provider_version_id, (string) $provider->id)) {
            $this->deny($denialReason);
        }

        $newEvidence = $decision->run_result_checksum !== null
            || $decision->artifact_sha !== null
            || $run->result_checksum !== null;
        if ($newEvidence) {
            $evaluationConfiguration = $this->jsonObject($evaluationVersion->configuration);
            foreach ([
                [$decision->release_policy_checksum, $run->release_policy_checksum],
                [$decision->release_policy_checksum, $policy->checksum],
                [$decision->evaluation_version_checksum, $run->evaluation_version_checksum],
                [$decision->evaluation_version_checksum, $evaluationVersion->checksum],
                [$decision->candidate_checksum, $run->candidate_checksum],
                [$decision->prompt_checksum, $run->prompt_checksum],
                [$decision->prompt_checksum, $prompt->checksum],
                [$decision->provider_checksum, $run->provider_checksum],
                [$decision->provider_checksum, $provider->checksum],
                [$decision->model_checksum, $run->model_checksum],
                [$decision->model_checksum, $model->checksum],
                [$decision->dataset_checksum, $run->dataset_checksum],
                [$decision->dataset_checksum, $evaluationConfiguration['dataset_checksum'] ?? null],
                [$decision->evaluator_checksum, $run->evaluator_checksum],
                [$decision->evaluator_checksum, $evaluationConfiguration['evaluator_checksum'] ?? null],
                [$decision->run_result_checksum, $run->result_checksum],
            ] as [$left, $right]) {
                if (! $this->sameChecksum($left, $right)) {
                    $this->deny($denialReason);
                }
            }
            foreach ([
                [$decision->prompt_version_id, $run->prompt_version_id],
                [$decision->provider_version_id, $run->provider_version_id],
                [$decision->model_version_id, $run->model_version_id],
                [$decision->release_policy_id, $run->release_policy_id],
            ] as [$left, $right]) {
                if (! is_string($left) || ! is_string($right) || ! hash_equals($left, $right)) {
                    $this->deny($denialReason);
                }
            }
            if (! is_string($decision->artifact_sha)
                || ! is_string($run->artifact_sha)
                || ! hash_equals($decision->artifact_sha, $run->artifact_sha)
                || ! is_string($decision->evaluator_version)
                || ! is_string($run->evaluator_version)
                || ! hash_equals($decision->evaluator_version, $run->evaluator_version)
                || ! hash_equals((string) ($evaluationConfiguration['evaluator_version'] ?? ''), $run->evaluator_version)) {
                $this->deny($denialReason);
            }
            $requestChecksum = EvaluationRequestEvidence::checksum([
                'tenant_id' => $tenantId,
                'evaluation_version_id' => (string) $run->evaluation_version_id,
                'evaluation_version_checksum' => (string) $run->evaluation_version_checksum,
                'release_policy_id' => (string) $run->release_policy_id,
                'release_policy_checksum' => (string) $run->release_policy_checksum,
                'agent_version_id' => (string) $run->agent_version_id,
                'candidate_checksum' => (string) $run->candidate_checksum,
                'prompt_version_id' => (string) $run->prompt_version_id,
                'prompt_checksum' => (string) $run->prompt_checksum,
                'provider_version_id' => (string) $run->provider_version_id,
                'provider_checksum' => (string) $run->provider_checksum,
                'model_version_id' => (string) $run->model_version_id,
                'model_checksum' => (string) $run->model_checksum,
                'dataset_checksum' => (string) $run->dataset_checksum,
                'evaluator_version' => (string) $run->evaluator_version,
                'evaluator_checksum' => (string) $run->evaluator_checksum,
                'deterministic_seed' => (int) $run->deterministic_seed,
                'baseline_run_id' => $run->baseline_run_id,
                'artifact_sha' => (string) $run->artifact_sha,
            ]);
            if (! $this->sameChecksum($run->request_checksum, $requestChecksum)
                || ! $this->sameChecksum($decision->evidence_hash, EvaluationDecisionEvidence::checksum($run))) {
                $this->deny($denialReason);
            }
            $integrity = $this->runEvidence->assert($run, $lockForUpdate);
            if ($integrity['blocking_codes'] !== [] || ! $integrity['all_passed']
                || (int) $run->blocking_failures !== 0) {
                $this->deny($denialReason);
            }
        }

        if (($agentConfiguration['audience'] ?? null) === 'public'
            && (! hash_equals('platform_public', (string) $agentDefinition->visibility)
                || ! hash_equals('platform_public', (string) $promptDefinition->visibility))) {
            $this->deny($denialReason);
        }

        $caseIds = $this->read(DB::table('ai_evaluation_cases')
            ->where('tenant_id', $tenantId)
            ->where('evaluation_version_id', $evaluationVersion->id), $lockForUpdate)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
        $results = $this->read(DB::table('ai_evaluation_results')
            ->where('tenant_id', $tenantId)
            ->where('evaluation_run_id', $run->id), $lockForUpdate)
            ->get();
        if ($caseIds === [] || $results->count() !== count($caseIds)) {
            $this->deny($denialReason);
        }
        $resultCaseIds = [];
        foreach ($results as $result) {
            $caseId = (string) $result->evaluation_case_id;
            if (! in_array($caseId, $caseIds, true)
                || in_array($caseId, $resultCaseIds, true)
                || ! hash_equals('passed', (string) $result->status)
                || ! $this->atOrBefore($run->started_at, $result->completed_at)
                || ! $this->atOrBefore($result->completed_at, $run->completed_at)
                || ! $this->atOrBefore($result->completed_at, $decision->decided_at)
                || ! $this->atOrBefore($result->completed_at, now())) {
                $this->deny($denialReason);
            }
            $resultCaseIds[] = $caseId;
        }
        sort($caseIds);
        sort($resultCaseIds);
        if ($caseIds !== $resultCaseIds) {
            $this->deny($denialReason);
        }

        return new AiReleaseCandidateSet(
            decision: $decision,
            agent: $agent,
            agentDefinition: $agentDefinition,
            prompt: $prompt,
            promptDefinition: $promptDefinition,
            model: $model,
            modelDefinition: $modelDefinition,
            provider: $provider,
            providerDefinition: $providerDefinition,
            agentConfiguration: $agentConfiguration,
            modelConfiguration: $this->jsonObject($model->configuration),
            providerConfiguration: $this->jsonObject($provider->configuration),
            policyRules: $this->jsonObject($policy->rules),
        );
    }

    public function assertDecisionStillEligible(
        string $tenantId,
        AiReleaseCandidateSet $set,
        string $denialReason,
    ): void {
        $decision = DB::table('ai_release_decisions')
            ->where('tenant_id', $tenantId)
            ->where('id', $set->decision->id)
            ->lockForUpdate()
            ->first();
        $policy = DB::table('ai_release_policies')
            ->where('tenant_id', $tenantId)
            ->where('id', $set->decision->release_policy_id)
            ->lockForUpdate()
            ->first();
        if ($decision === null
            || $policy === null
            || ! hash_equals('passed', (string) $decision->decision)
            || $decision->revoked_at !== null
            || ! hash_equals('active', (string) $policy->status)
            || ! hash_equals(
                (string) $set->decision->candidate_checksum,
                (string) $decision->candidate_checksum,
            )
            || ! hash_equals(
                (string) $set->decision->updated_at,
                (string) $decision->updated_at,
            )) {
            $this->deny($denialReason);
        }
    }

    private function atOrBefore(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        try {
            return Carbon::parse((string) $left)->lessThanOrEqualTo(Carbon::parse((string) $right));
        } catch (\Throwable) {
            return false;
        }
    }

    private function before(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        try {
            return Carbon::parse((string) $left)->lessThan(Carbon::parse((string) $right));
        } catch (\Throwable) {
            return false;
        }
    }

    private function sameChecksum(mixed $left, mixed $right): bool
    {
        return is_string($left) && is_string($right)
            && preg_match('/^[a-f0-9]{64}$/', $left) === 1
            && hash_equals($left, $right);
    }

    private function read(Builder $query, bool $lockForUpdate): Builder
    {
        return $lockForUpdate ? $query->lockForUpdate() : $query;
    }

    /** @return array<string, mixed> */
    private function jsonObject(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : [];
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }
        $items = array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item) && Str::isUuid($item),
        ));

        return count($items) === count($value) && count(array_unique($items)) === count($items)
            ? $items
            : [];
    }

    private function deny(string $reason): never
    {
        throw new AiExecutionDeniedException($reason);
    }
}
