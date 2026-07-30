<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use App\Application\Ai\Security\EncryptedAiContent;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Infrastructure\AI\Security\LaravelAiContentCipher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AiRegistryResolver
{
    public function __construct(
        private readonly LaravelAiContentCipher $cipher,
    ) {}

    public function resolve(AiRegistryQuery $query): AiRuntimeSelection
    {
        if ($query->tenantId === null) {
            throw new AiExecutionDeniedException('tenant_context');
        }

        $activation = $this->activation($query);
        $bucket = hexdec(substr(hash('sha256', $activation->cohort_salt.'|'.$query->cohortSubject), 0, 8)) % 100;
        if ($bucket >= (int) $activation->rollout_percentage) {
            if (! is_string($activation->previous_activation_id) || ! Str::isUuid($activation->previous_activation_id)) {
                throw new AiExecutionDeniedException('registry_selection');
            }

            $activation = DB::table('ai_agent_activations')
                ->where('tenant_id', $query->tenantId)
                ->where('id', $activation->previous_activation_id)
                ->first();
            if ($activation === null) {
                throw new AiExecutionDeniedException('registry_selection');
            }
        }

        return $this->selection($query, $activation);
    }

    private function activation(AiRegistryQuery $query): object
    {
        $active = DB::table('ai_agent_activations')
            ->where('tenant_id', $query->tenantId)
            ->where('effective_from', '<=', now())
            ->where(static function ($builder): void {
                $builder->whereNull('effective_until')->orWhere('effective_until', '>', now());
            })
            ->get();

        foreach ($this->scopes($query) as [$scopeType, $scopeKey]) {
            $matching = $active->filter(static fn (object $activation): bool => (
                hash_equals($scopeType, (string) $activation->scope_type)
                && hash_equals($scopeKey, (string) $activation->scope_key)
            ));
            if ($matching->count() > 1) {
                throw new AiExecutionDeniedException('registry_conflict');
            }

            $activation = $matching->first();
            if ($activation !== null) {
                return $activation;
            }
        }

        throw new AiExecutionDeniedException('registry_selection');
    }

    /** @return list<array{string, string}> */
    private function scopes(AiRegistryQuery $query): array
    {
        return [
            ['tenant_channel', $query->tenantId.'|'.$query->channel],
            ['tenant', $query->tenantId],
            ['market_channel', $query->marketCountryCode.'|'.$query->channel],
            ['market', $query->marketCountryCode],
            ['platform_channel', $query->channel],
            ['platform', '*'],
        ];
    }

    private function selection(AiRegistryQuery $query, object $activation): AiRuntimeSelection
    {
        $decision = $this->passingDecision($query, $activation);
        $agent = DB::table('ai_agent_versions as versions')
            ->join('ai_agent_definitions as definitions', function ($join): void {
                $join->on('definitions.tenant_id', '=', 'versions.tenant_id')
                    ->on('definitions.id', '=', 'versions.agent_definition_id');
            })
            ->where('versions.tenant_id', $query->tenantId)
            ->where('versions.id', $activation->agent_version_id)
            ->where('definitions.status', 'active')
            ->where('versions.lifecycle_status', 'active')
            ->where('versions.kill_switch_enabled', false)
            ->whereNotNull('versions.released_at')
            ->select([
                'versions.id',
                'versions.checksum',
                'versions.configuration',
                'definitions.visibility',
            ])
            ->first();
        if ($agent === null) {
            throw new AiExecutionDeniedException('registry_selection');
        }
        $expectedVisibility = $this->definitionVisibility($query->audience);
        if (
            $expectedVisibility === null
            || ! hash_equals($expectedVisibility, (string) $agent->visibility)
            || ! is_string($decision->candidate_checksum)
            || ! hash_equals((string) $agent->checksum, $decision->candidate_checksum)
        ) {
            throw new AiExecutionDeniedException('registry_selection');
        }

        $configuration = $this->jsonObject($agent->configuration);
        $requiredCapabilities = $this->stringList($configuration['required_capabilities'] ?? null);
        $modelVersionIds = $this->stringList($configuration['model_version_ids'] ?? null);
        $channels = $this->stringList($configuration['channels'] ?? null);
        $promptVersionId = $configuration['prompt_version_id'] ?? null;
        $outputSchema = $configuration['output_schema'] ?? null;
        $maxOutputTokens = $configuration['max_output_tokens'] ?? null;
        if (
            ($configuration['capability'] ?? null) !== $query->capability
            || ($configuration['audience'] ?? null) !== $query->audience
            || ! in_array($query->channel, $channels, true)
            || ! is_string($promptVersionId)
            || ! Str::isUuid($promptVersionId)
            || ! is_string($outputSchema)
            || ! is_int($maxOutputTokens)
            || $requiredCapabilities === []
            || $modelVersionIds === []
        ) {
            throw new AiExecutionDeniedException('registry_selection');
        }

        $policyRules = $this->jsonObject($decision->rules);
        if (! $this->policyCompatible($policyRules, $query, $requiredCapabilities)) {
            throw new AiExecutionDeniedException('registry_selection');
        }

        $model = null;
        foreach ($modelVersionIds as $modelVersionId) {
            $candidate = $this->eligibleModel($modelVersionId, $query, $requiredCapabilities);
            if ($candidate !== null) {
                $model = $candidate;
                break;
            }
        }
        if ($model === null) {
            throw new AiExecutionDeniedException('registry_selection');
        }

        $prompt = DB::table('ai_prompt_versions as versions')
            ->join('ai_prompt_definitions as definitions', function ($join): void {
                $join->on('definitions.tenant_id', '=', 'versions.tenant_id')
                    ->on('definitions.id', '=', 'versions.prompt_definition_id');
            })
            ->where('versions.tenant_id', $query->tenantId)
            ->where('versions.id', $promptVersionId)
            ->where('definitions.status', 'active')
            ->whereIn('versions.lifecycle_status', ['eligible', 'active'])
            ->where('versions.kill_switch_enabled', false)
            ->whereNotNull('versions.released_at')
            ->select([
                'versions.id',
                'versions.template_ciphertext',
                'versions.template_hash',
                'versions.encryption_key_version',
                'definitions.visibility',
            ])
            ->first();
        if (
            $prompt === null
            || ! hash_equals($expectedVisibility, (string) $prompt->visibility)
        ) {
            throw new AiExecutionDeniedException('registry_selection');
        }

        $this->assertPassingEvaluation(
            $query,
            $decision,
            (string) $agent->id,
            (string) $prompt->id,
            (string) $model->provider_version_id,
            (string) $model->model_version_id,
        );

        $systemPrompt = $this->cipher->decrypt(
            $query->tenantId,
            $prompt->id,
            'prompt_template',
            new EncryptedAiContent(
                ciphertext: $prompt->template_ciphertext,
                contentHash: $prompt->template_hash,
                keyVersion: $prompt->encryption_key_version,
            ),
        );

        return new AiRuntimeSelection(
            providerVersionId: $model->provider_version_id,
            modelVersionId: $model->model_version_id,
            agentVersionId: $agent->id,
            promptVersionId: $prompt->id,
            providerKey: $model->provider_key,
            adapterKey: $model->adapter_key,
            secretReference: $model->secret_reference,
            modelKey: $model->model_key,
            systemPrompt: $systemPrompt,
            outputSchema: $outputSchema,
            requiredCapabilities: $requiredCapabilities,
            maxOutputTokens: $maxOutputTokens,
            releaseDecisionId: $decision->id,
        );
    }

    private function passingDecision(AiRegistryQuery $query, object $activation): object
    {
        if (! is_string($activation->release_decision_id) || ! Str::isUuid($activation->release_decision_id)) {
            throw new AiExecutionDeniedException('registry_selection');
        }

        $latestDecisions = DB::table('ai_release_decisions')
            ->where('tenant_id', $query->tenantId)
            ->where('agent_version_id', $activation->agent_version_id)
            ->orderByDesc('decided_at')
            ->limit(2)
            ->get();
        $latest = $latestDecisions->first();
        if (
            $latest === null
            || $latestDecisions->count() > 1
                && hash_equals(
                    (string) $latest->decided_at,
                    (string) $latestDecisions->get(1)->decided_at,
                )
            || ! hash_equals((string) $activation->release_decision_id, (string) $latest->id)
            || ! hash_equals('passed', (string) $latest->decision)
        ) {
            throw new AiExecutionDeniedException('registry_selection');
        }

        $decision = DB::table('ai_release_decisions as decisions')
            ->join('ai_release_policies as policies', function ($join): void {
                $join->on('policies.tenant_id', '=', 'decisions.tenant_id')
                    ->on('policies.id', '=', 'decisions.release_policy_id');
            })
            ->where('decisions.tenant_id', $query->tenantId)
            ->where('decisions.id', $activation->release_decision_id)
            ->where('decisions.agent_version_id', $activation->agent_version_id)
            ->where('decisions.decision', 'passed')
            ->where('policies.status', 'active')
            ->whereNotNull('decisions.evaluation_run_id')
            ->whereNotNull('decisions.candidate_checksum')
            ->whereNotNull('decisions.valid_from')
            ->where('decisions.valid_from', '<=', now())
            ->whereNull('decisions.revoked_at')
            ->where(static function ($builder): void {
                $builder->whereNull('decisions.valid_until')
                    ->orWhere('decisions.valid_until', '>', now());
            })
            ->where('decisions.decided_at', '<=', now())
            ->select([
                'decisions.id',
                'decisions.evaluation_run_id',
                'decisions.candidate_checksum',
                'decisions.decided_at',
                'policies.rules',
            ])
            ->first();
        if ($decision === null) {
            throw new AiExecutionDeniedException('registry_selection');
        }

        return $decision;
    }

    private function assertPassingEvaluation(
        AiRegistryQuery $query,
        object $decision,
        string $agentVersionId,
        string $promptVersionId,
        string $providerVersionId,
        string $modelVersionId,
    ): void {
        if (
            ! is_string($decision->evaluation_run_id)
            || ! Str::isUuid($decision->evaluation_run_id)
        ) {
            throw new AiExecutionDeniedException('registry_selection');
        }

        $run = DB::table('ai_evaluation_runs as runs')
            ->join('ai_evaluation_versions as versions', function ($join): void {
                $join->on('versions.tenant_id', '=', 'runs.tenant_id')
                    ->on('versions.id', '=', 'runs.evaluation_version_id');
            })
            ->join('ai_evaluation_suites as suites', function ($join): void {
                $join->on('suites.tenant_id', '=', 'versions.tenant_id')
                    ->on('suites.id', '=', 'versions.evaluation_suite_id');
            })
            ->where('runs.tenant_id', $query->tenantId)
            ->where('runs.id', $decision->evaluation_run_id)
            ->where('runs.status', 'completed')
            ->where('suites.status', 'active')
            ->whereNotNull('versions.released_at')
            ->whereNotNull('runs.started_at')
            ->whereNotNull('runs.completed_at')
            ->where('versions.released_at', '<=', $decision->decided_at)
            ->whereColumn('runs.started_at', '<=', 'runs.completed_at')
            ->where('runs.completed_at', '<=', $decision->decided_at)
            ->where('runs.completed_at', '<=', now())
            ->select([
                'runs.evaluation_version_id',
                'runs.agent_version_id',
                'runs.prompt_version_id',
                'runs.provider_version_id',
                'runs.model_version_id',
            ])
            ->first();
        if (
            $run === null
            || ! is_string($run->agent_version_id)
            || ! is_string($run->prompt_version_id)
            || ! is_string($run->provider_version_id)
            || ! is_string($run->model_version_id)
            || ! hash_equals($agentVersionId, $run->agent_version_id)
            || ! hash_equals($promptVersionId, $run->prompt_version_id)
            || ! hash_equals($providerVersionId, $run->provider_version_id)
            || ! hash_equals($modelVersionId, $run->model_version_id)
        ) {
            throw new AiExecutionDeniedException('registry_selection');
        }

        $caseCount = DB::table('ai_evaluation_cases')
            ->where('tenant_id', $query->tenantId)
            ->where('evaluation_version_id', $run->evaluation_version_id)
            ->count();
        $resultCount = DB::table('ai_evaluation_results as results')
            ->join('ai_evaluation_cases as cases', function ($join): void {
                $join->on('cases.tenant_id', '=', 'results.tenant_id')
                    ->on('cases.id', '=', 'results.evaluation_case_id');
            })
            ->where('results.tenant_id', $query->tenantId)
            ->where('results.evaluation_run_id', $decision->evaluation_run_id)
            ->where('cases.evaluation_version_id', $run->evaluation_version_id)
            ->count();
        $passedCount = DB::table('ai_evaluation_results as results')
            ->join('ai_evaluation_cases as cases', function ($join): void {
                $join->on('cases.tenant_id', '=', 'results.tenant_id')
                    ->on('cases.id', '=', 'results.evaluation_case_id');
            })
            ->where('results.tenant_id', $query->tenantId)
            ->where('results.evaluation_run_id', $decision->evaluation_run_id)
            ->where('cases.evaluation_version_id', $run->evaluation_version_id)
            ->where('results.status', 'passed')
            ->whereNotNull('results.completed_at')
            ->where('results.completed_at', '<=', $decision->decided_at)
            ->where('results.completed_at', '<=', now())
            ->count();
        if ($caseCount < 1 || $resultCount !== $caseCount || $passedCount !== $caseCount) {
            throw new AiExecutionDeniedException('registry_selection');
        }
    }

    private function definitionVisibility(string $audience): ?string
    {
        return match ($audience) {
            'public' => 'platform_public',
            'private' => 'tenant_private',
            default => null,
        };
    }

    private function eligibleModel(
        string $modelVersionId,
        AiRegistryQuery $query,
        array $requiredCapabilities,
    ): ?object {
        if (! Str::isUuid($modelVersionId)) {
            return null;
        }

        $model = DB::table('ai_model_versions as versions')
            ->join('ai_models as models', 'models.id', '=', 'versions.model_id')
            ->join('ai_provider_versions as provider_versions', function ($join): void {
                $join->on('provider_versions.id', '=', 'versions.provider_version_id')
                    ->on('provider_versions.provider_id', '=', 'models.provider_id');
            })
            ->join('ai_providers as providers', 'providers.id', '=', 'models.provider_id')
            ->where('versions.id', $modelVersionId)
            ->where('models.status', 'active')
            ->where('providers.status', 'active')
            ->where('provider_versions.status', 'active')
            ->whereIn('versions.lifecycle_status', ['eligible', 'active'])
            ->where('versions.kill_switch_enabled', false)
            ->whereIn('provider_versions.lifecycle_status', ['eligible', 'active'])
            ->where('provider_versions.kill_switch_enabled', false)
            ->whereNotNull('versions.released_at')
            ->whereNotNull('provider_versions.released_at')
            ->select([
                'versions.id as model_version_id',
                'versions.configuration as model_configuration',
                'provider_versions.id as provider_version_id',
                'provider_versions.secret_reference',
                'provider_versions.configuration as provider_configuration',
                'providers.provider_key',
                'providers.adapter_key',
                'models.key as model_key',
            ])
            ->first();
        if ($model === null || ! is_string($model->secret_reference) || trim($model->secret_reference) === '') {
            return null;
        }

        $providerConfiguration = $this->jsonObject($model->provider_configuration);
        $modelConfiguration = $this->jsonObject($model->model_configuration);
        if (
            ($providerConfiguration['enabled'] ?? null) !== true
            || ! $this->routingCompatible($providerConfiguration, $query, $requiredCapabilities)
            || ! $this->routingCompatible($modelConfiguration, $query, $requiredCapabilities)
        ) {
            return null;
        }

        return $model;
    }

    private function policyCompatible(
        array $rules,
        AiRegistryQuery $query,
        array $requiredCapabilities,
    ): bool {
        $policyCapabilities = $this->stringList($rules['required_capabilities'] ?? null);

        return $policyCapabilities !== []
            && $this->containsEvery($requiredCapabilities, $policyCapabilities)
            && $this->countryAllowed($rules, 'allowed_market_country_codes', $query->marketCountryCode)
            && $this->countryAllowed($rules, 'residency_country_codes', $query->marketCountryCode);
    }

    private function routingCompatible(
        array $configuration,
        AiRegistryQuery $query,
        array $requiredCapabilities,
    ): bool {
        $capabilities = $this->stringList($configuration['capabilities'] ?? null);

        return $capabilities !== []
            && $this->containsEvery($capabilities, $requiredCapabilities)
            && $this->countryAllowed($configuration, 'allowed_market_country_codes', $query->marketCountryCode)
            && $this->countryAllowed($configuration, 'residency_country_codes', $query->marketCountryCode);
    }

    private function countryAllowed(array $policy, string $key, string $countryCode): bool
    {
        $countries = $this->stringList($policy[$key] ?? null);

        return $countries !== [] && in_array($countryCode, $countries, true);
    }

    /** @param list<string> $haystack @param list<string> $needles */
    private function containsEvery(array $haystack, array $needles): bool
    {
        return array_diff($needles, $haystack) === [];
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

        $strings = array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item) && trim($item) !== '',
        ));

        return count($strings) === count($value) && count(array_unique($strings)) === count($strings)
            ? $strings
            : [];
    }
}
