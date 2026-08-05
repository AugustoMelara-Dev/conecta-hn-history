<?php

declare(strict_types=1);

namespace App\Application\Ai\Evaluation;

use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Application\Ai\Security\AiContentCipher;
use App\Application\Ai\Security\EncryptedAiContent;
use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AiEvaluationSelectionResolver
{
    private const PRE_RELEASE_DECISION_ID = '00000000-0000-4000-8000-000000000000';

    public function __construct(private readonly AiContentCipher $cipher) {}

    public function resolve(
        string $evaluationVersionId,
        string $agentVersionId,
        string $providerVersionId,
        string $modelVersionId,
        ?string $promptVersionId = null,
    ): AiRuntimeSelection {
        foreach ([$evaluationVersionId, $agentVersionId, $providerVersionId, $modelVersionId] as $id) {
            if (! Str::isUuid($id)) {
                $this->deny();
            }
        }
        $evaluation = DB::table('ai_evaluation_versions')->where('id', $evaluationVersionId)->first();
        if ($evaluation === null) {
            $this->deny();
        }
        $tenantId = (string) $evaluation->tenant_id;
        $agent = DB::table('ai_agent_versions')->where('tenant_id', $tenantId)->where('id', $agentVersionId)->first();
        $provider = DB::table('ai_provider_versions')->where('id', $providerVersionId)->first();
        $model = DB::table('ai_model_versions')->where('id', $modelVersionId)->first();
        if ($agent === null || $provider === null || $model === null) {
            $this->deny();
        }
        $agentConfiguration = $this->object($agent->configuration);
        $configuredPromptId = $agentConfiguration['prompt_version_id'] ?? null;
        if (! is_string($configuredPromptId) || ! Str::isUuid($configuredPromptId)
            || ($promptVersionId !== null && ! hash_equals($configuredPromptId, $promptVersionId))) {
            $this->deny();
        }
        $prompt = DB::table('ai_prompt_versions')->where('tenant_id', $tenantId)->where('id', $configuredPromptId)->first();
        $providerDefinition = DB::table('ai_providers')->where('id', $provider->provider_id)->where('status', 'active')->first();
        $modelDefinition = DB::table('ai_models')->where('id', $model->model_id)->where('status', 'active')->first();
        $requiredCapabilities = $agentConfiguration['required_capabilities'] ?? null;
        $outputSchema = $agentConfiguration['output_schema'] ?? null;
        $maxOutputTokens = $agentConfiguration['max_output_tokens'] ?? null;
        if ($prompt === null || $providerDefinition === null || $modelDefinition === null
            || ! is_array($requiredCapabilities) || ! array_is_list($requiredCapabilities)
            || ! is_string($outputSchema) || ! is_int($maxOutputTokens)
            || ! in_array($modelVersionId, $agentConfiguration['model_version_ids'] ?? [], true)
            || ! hash_equals((string) $agent->model_version_id, $modelVersionId)
            || ! hash_equals((string) $model->provider_version_id, $providerVersionId)
            || ! hash_equals((string) $modelDefinition->provider_id, (string) $provider->provider_id)) {
            $this->deny();
        }
        $systemPrompt = $this->cipher->decrypt(
            $tenantId,
            $configuredPromptId,
            'prompt_template',
            new EncryptedAiContent(
                (string) $prompt->template_ciphertext,
                (string) $prompt->template_hash,
                (string) $prompt->encryption_key_version,
            ),
        );

        return new AiRuntimeSelection(
            providerVersionId: $providerVersionId,
            modelVersionId: $modelVersionId,
            agentVersionId: $agentVersionId,
            promptVersionId: $configuredPromptId,
            providerKey: (string) $providerDefinition->provider_key,
            adapterKey: (string) $providerDefinition->adapter_key,
            secretReference: (string) $provider->secret_reference,
            modelKey: (string) $modelDefinition->key,
            systemPrompt: $systemPrompt,
            outputSchema: $outputSchema,
            requiredCapabilities: $requiredCapabilities,
            maxOutputTokens: $maxOutputTokens,
            releaseDecisionId: self::PRE_RELEASE_DECISION_ID,
        );
    }

    /** @return array<string, mixed> */
    private function object(mixed $raw): array
    {
        if (is_string($raw)) {
            try {
                $raw = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $this->deny();
            }
        }
        if (! is_array($raw) || array_is_list($raw)) {
            $this->deny();
        }

        return $raw;
    }

    private function deny(): never
    {
        throw new AiExecutionDeniedException('evaluation_gate');
    }
}
