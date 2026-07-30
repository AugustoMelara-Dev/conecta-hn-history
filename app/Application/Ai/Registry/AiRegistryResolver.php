<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use App\Application\Ai\Security\EncryptedAiContent;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Infrastructure\AI\Security\LaravelAiContentCipher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class AiRegistryResolver
{
    public function __construct(
        private LaravelAiContentCipher $cipher,
        private AiReleaseEligibility $eligibility,
        private AiReleaseCompatibility $compatibility,
    ) {}

    public function resolve(AiRegistryQuery $query): AiRuntimeSelection
    {
        if ($query->tenantId === null) {
            throw new AiExecutionDeniedException('tenant_context');
        }

        $activation = $this->activation($query);
        $bucket = hexdec(substr(
            hash('sha256', $activation->cohort_salt.'|'.$query->cohortSubject),
            0,
            8,
        )) % 100;
        if ($bucket >= (int) $activation->rollout_percentage) {
            if (! is_string($activation->previous_activation_id)
                || ! Str::isUuid($activation->previous_activation_id)) {
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
            if (($activation = $matching->first()) !== null) {
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
        if (! is_string($activation->release_decision_id)
            || ! Str::isUuid($activation->release_decision_id)) {
            throw new AiExecutionDeniedException('registry_selection');
        }
        $set = $this->eligibility->assertExact(
            tenantId: (string) $query->tenantId,
            decisionId: $activation->release_decision_id,
            allowedLifecycleStatuses: ['eligible', 'active'],
            denialReason: 'registry_selection',
            expectedAgentVersionId: (string) $activation->agent_version_id,
            lockForUpdate: false,
        );
        if (! hash_equals('active', (string) $set->agent->lifecycle_status)) {
            throw new AiExecutionDeniedException('registry_selection');
        }

        $match = $this->compatibility->assertForRuntime($set, $query);

        $systemPrompt = $this->cipher->decrypt(
            (string) $query->tenantId,
            (string) $set->prompt->id,
            'prompt_template',
            new EncryptedAiContent(
                ciphertext: (string) $set->prompt->template_ciphertext,
                contentHash: (string) $set->prompt->template_hash,
                keyVersion: (string) $set->prompt->encryption_key_version,
            ),
        );

        return new AiRuntimeSelection(
            providerVersionId: (string) $set->provider->id,
            modelVersionId: (string) $set->model->id,
            agentVersionId: (string) $set->agent->id,
            promptVersionId: (string) $set->prompt->id,
            providerKey: (string) $set->providerDefinition->provider_key,
            adapterKey: (string) $set->providerDefinition->adapter_key,
            secretReference: (string) $set->provider->secret_reference,
            modelKey: (string) $set->modelDefinition->key,
            systemPrompt: $systemPrompt,
            outputSchema: $match['output_schema'],
            requiredCapabilities: $match['required_capabilities'],
            maxOutputTokens: $match['max_output_tokens'],
            releaseDecisionId: (string) $set->decision->id,
        );
    }
}
