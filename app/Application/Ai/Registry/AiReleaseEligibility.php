<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AiReleaseEligibility
{
    /**
     * @param  list<string>  $allowedLifecycleStatuses
     */
    public function assertExact(
        string $tenantId,
        string $decisionId,
        array $allowedLifecycleStatuses,
        string $denialReason,
        ?string $expectedAgentVersionId = null,
    ): AiReleaseCandidateSet {
        if (! Str::isUuid($tenantId)
            || ! Str::isUuid($decisionId)
            || $allowedLifecycleStatuses === []) {
            $this->deny($denialReason);
        }

        $decision = DB::table('ai_release_decisions')
            ->where('tenant_id', $tenantId)
            ->where('id', $decisionId)
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

        $latest = DB::table('ai_release_decisions')
            ->where('tenant_id', $tenantId)
            ->where('agent_version_id', $decision->agent_version_id)
            ->orderByDesc('decided_at')
            ->orderByDesc('id')
            ->limit(2)
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

        $policy = DB::table('ai_release_policies')
            ->where('tenant_id', $tenantId)
            ->where('id', $decision->release_policy_id)
            ->where('status', 'active')
            ->first();
        $run = DB::table('ai_evaluation_runs')
            ->where('tenant_id', $tenantId)
            ->where('id', $decision->evaluation_run_id)
            ->where('status', 'completed')
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

        $evaluationVersion = DB::table('ai_evaluation_versions')
            ->where('tenant_id', $tenantId)
            ->where('id', $run->evaluation_version_id)
            ->first();
        if ($evaluationVersion === null
            || $evaluationVersion->released_at === null
            || ! $this->atOrBefore($evaluationVersion->released_at, $decision->decided_at)
            || ! DB::table('ai_evaluation_suites')
                ->where('tenant_id', $tenantId)
                ->where('id', $evaluationVersion->evaluation_suite_id)
                ->where('status', 'active')
                ->exists()) {
            $this->deny($denialReason);
        }

        $agent = DB::table('ai_agent_versions')
            ->where('tenant_id', $tenantId)
            ->where('id', $decision->agent_version_id)
            ->first();
        if ($agent === null
            || ! in_array((string) $agent->lifecycle_status, $allowedLifecycleStatuses, true)
            || (bool) $agent->kill_switch_enabled
            || ! hash_equals((string) $agent->checksum, (string) $decision->candidate_checksum)) {
            $this->deny($denialReason);
        }
        $agentDefinition = DB::table('ai_agent_definitions')
            ->where('tenant_id', $tenantId)
            ->where('id', $agent->agent_definition_id)
            ->where('status', 'active')
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

        $prompt = DB::table('ai_prompt_versions')
            ->where('tenant_id', $tenantId)
            ->where('id', $run->prompt_version_id)
            ->first();
        $promptDefinition = $prompt === null
            ? null
            : DB::table('ai_prompt_definitions')
                ->where('tenant_id', $tenantId)
                ->where('id', $prompt->prompt_definition_id)
                ->where('status', 'active')
                ->first();
        $model = DB::table('ai_model_versions')
            ->where('id', $run->model_version_id)
            ->first();
        $modelDefinition = $model === null
            ? null
            : DB::table('ai_models')
                ->where('id', $model->model_id)
                ->where('status', 'active')
                ->first();
        $provider = DB::table('ai_provider_versions')
            ->where('id', $run->provider_version_id)
            ->first();
        $providerDefinition = $provider === null
            ? null
            : DB::table('ai_providers')
                ->where('id', $provider->provider_id)
                ->where('status', 'active')
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

        $caseIds = DB::table('ai_evaluation_cases')
            ->where('tenant_id', $tenantId)
            ->where('evaluation_version_id', $evaluationVersion->id)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
        $results = DB::table('ai_evaluation_results')
            ->where('tenant_id', $tenantId)
            ->where('evaluation_run_id', $run->id)
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
