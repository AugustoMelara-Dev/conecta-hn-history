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
        );
        if (! hash_equals('active', (string) $set->agent->lifecycle_status)) {
            throw new AiExecutionDeniedException('registry_selection');
        }

        $expectedVisibility = $this->definitionVisibility($query->audience);
        $requiredCapabilities = $this->stringList(
            $set->agentConfiguration['required_capabilities'] ?? null,
        );
        $modelVersionIds = $this->stringList($set->agentConfiguration['model_version_ids'] ?? null);
        $channels = $this->stringList($set->agentConfiguration['channels'] ?? null);
        $outputSchema = $set->agentConfiguration['output_schema'] ?? null;
        $maxOutputTokens = $set->agentConfiguration['max_output_tokens'] ?? null;
        if ($expectedVisibility === null
            || ! hash_equals($expectedVisibility, (string) $set->agentDefinition->visibility)
            || ! hash_equals($expectedVisibility, (string) $set->promptDefinition->visibility)
            || ($set->agentConfiguration['capability'] ?? null) !== $query->capability
            || ($set->agentConfiguration['audience'] ?? null) !== $query->audience
            || ! in_array($query->channel, $channels, true)
            || ! in_array((string) $set->model->id, $modelVersionIds, true)
            || ! is_string($outputSchema)
            || trim($outputSchema) === ''
            || ! is_int($maxOutputTokens)
            || $maxOutputTokens < 1
            || $requiredCapabilities === []
            || ! $this->routingCompatible(
                $set->providerConfiguration,
                $query,
                $requiredCapabilities,
            )
            || ! $this->routingCompatible(
                $set->modelConfiguration,
                $query,
                $requiredCapabilities,
            )
            || ! $this->policyCompatible($set->policyRules, $query, $requiredCapabilities)
            || ! is_string($set->provider->secret_reference)
            || trim($set->provider->secret_reference) === '') {
            throw new AiExecutionDeniedException('registry_selection');
        }

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
            outputSchema: $outputSchema,
            requiredCapabilities: $requiredCapabilities,
            maxOutputTokens: $maxOutputTokens,
            releaseDecisionId: (string) $set->decision->id,
        );
    }

    private function definitionVisibility(string $audience): ?string
    {
        return match ($audience) {
            'public' => 'platform_public',
            'private' => 'tenant_private',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  list<string>  $requiredCapabilities
     */
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

    /**
     * @param  array<string, mixed>  $configuration
     * @param  list<string>  $requiredCapabilities
     */
    private function routingCompatible(
        array $configuration,
        AiRegistryQuery $query,
        array $requiredCapabilities,
    ): bool {
        $capabilities = $this->stringList($configuration['capabilities'] ?? null);

        return ($configuration['enabled'] ?? true) === true
            && $capabilities !== []
            && $this->containsEvery($capabilities, $requiredCapabilities)
            && $this->countryAllowed(
                $configuration,
                'allowed_market_country_codes',
                $query->marketCountryCode,
            )
            && $this->countryAllowed(
                $configuration,
                'residency_country_codes',
                $query->marketCountryCode,
            );
    }

    /** @param array<string, mixed> $configuration */
    private function countryAllowed(array $configuration, string $key, string $countryCode): bool
    {
        $countries = $this->stringList($configuration[$key] ?? null);

        return $countries !== [] && in_array($countryCode, $countries, true);
    }

    /** @param list<string> $haystack @param list<string> $needles */
    private function containsEvery(array $haystack, array $needles): bool
    {
        return array_diff($needles, $haystack) === [];
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
