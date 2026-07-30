<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use App\Application\Ai\Contracts\AiProviderPool;
use App\Exceptions\Ai\AiExecutionDeniedException;

final readonly class AiReleaseCompatibility
{
    public function __construct(private AiProviderPool $providerPool) {}

    /**
     * @return array{output_schema:string,required_capabilities:list<string>,max_output_tokens:int}
     */
    public function assertForActivation(
        AiReleaseCandidateSet $set,
        string $scopeType,
        string $scopeKey,
    ): array {
        $match = $this->assertSemantic($set, 'evaluation_gate');
        [$market, $channel] = match ($scopeType) {
            'market' => [$scopeKey, null],
            'market_channel' => explode('|', $scopeKey, 2),
            'platform_channel', 'tenant_channel' => [
                null,
                str_contains($scopeKey, '|')
                    ? explode('|', $scopeKey, 2)[1]
                    : $scopeKey,
            ],
            default => [null, null],
        };

        if ($channel !== null && ! $this->supportsChannel($set, $channel)) {
            $this->deny('evaluation_gate');
        }
        if ($market !== null && ! $this->supportsMarket($set, $market)) {
            $this->deny('evaluation_gate');
        }
        if (hash_equals('platform', $scopeType)
            && (! $this->supportsChannel($set, '*') || ! $this->supportsMarket($set, '*'))) {
            $this->deny('evaluation_gate');
        }
        if (hash_equals('platform_channel', $scopeType) && ! $this->supportsMarket($set, '*')) {
            $this->deny('evaluation_gate');
        }
        if (hash_equals('market', $scopeType) && ! $this->supportsChannel($set, '*')) {
            $this->deny('evaluation_gate');
        }

        return $match;
    }

    /**
     * @return array{output_schema:string,required_capabilities:list<string>,max_output_tokens:int}
     */
    public function assertForRuntime(
        AiReleaseCandidateSet $set,
        AiRegistryQuery $query,
    ): array {
        $match = $this->assertSemantic($set, 'registry_selection');
        if (! hash_equals(
            (string) $set->agentConfiguration['capability'],
            $query->capability,
        )
            || ! hash_equals(
                (string) $set->agentConfiguration['audience'],
                $query->audience,
            )
            || ! $this->supportsChannel($set, $query->channel)
            || ! $this->supportsMarket($set, $query->marketCountryCode)) {
            $this->deny('registry_selection');
        }

        return $match;
    }

    /**
     * @return array{output_schema:string,required_capabilities:list<string>,max_output_tokens:int}
     */
    private function assertSemantic(
        AiReleaseCandidateSet $set,
        string $denialReason,
    ): array {
        $configuration = $set->agentConfiguration;
        $audience = $configuration['audience'] ?? null;
        $capability = $configuration['capability'] ?? null;
        $channels = $this->stringList($configuration['channels'] ?? null);
        $modelVersionIds = $this->stringList($configuration['model_version_ids'] ?? null);
        $agentCapabilities = $this->stringList(
            $configuration['required_capabilities'] ?? null,
        );
        $policyCapabilities = $this->stringList(
            $set->policyRules['required_capabilities'] ?? null,
        );
        $requiredCapabilities = array_values(array_unique([
            ...$agentCapabilities,
            ...$policyCapabilities,
        ]));
        $outputSchema = $configuration['output_schema'] ?? null;
        $maxOutputTokens = $configuration['max_output_tokens'] ?? null;
        $expectedVisibility = match ($audience) {
            'public' => 'platform_public',
            'private' => 'tenant_private',
            default => null,
        };

        if ($expectedVisibility === null
            || ! is_string($capability)
            || trim($capability) === ''
            || $channels === []
            || $agentCapabilities === []
            || $policyCapabilities === []
            || $requiredCapabilities === []
            || ! is_string($outputSchema)
            || trim($outputSchema) === ''
            || ! is_int($maxOutputTokens)
            || $maxOutputTokens < 1
            || ! in_array((string) $set->model->id, $modelVersionIds, true)
            || ! hash_equals(
                (string) ($configuration['prompt_version_id'] ?? ''),
                (string) $set->prompt->id,
            )
            || ! hash_equals($expectedVisibility, (string) $set->agentDefinition->visibility)
            || ! hash_equals($expectedVisibility, (string) $set->promptDefinition->visibility)
            || ! $this->configurationSupports(
                $set->providerConfiguration,
                $requiredCapabilities,
            )
            || ! $this->configurationSupports(
                $set->modelConfiguration,
                $requiredCapabilities,
            )
            || ! $this->routingListsAreValid($set->policyRules)
            || ! is_string($set->provider->secret_reference)
            || trim($set->provider->secret_reference) === '') {
            $this->deny($denialReason);
        }

        try {
            $this->providerPool->resolve(
                (string) $set->providerDefinition->adapter_key,
                (string) $set->provider->secret_reference,
            );
        } catch (AiExecutionDeniedException) {
            $this->deny($denialReason);
        }

        return [
            'output_schema' => $outputSchema,
            'required_capabilities' => $requiredCapabilities,
            'max_output_tokens' => $maxOutputTokens,
        ];
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @param  list<string>  $requiredCapabilities
     */
    private function configurationSupports(
        array $configuration,
        array $requiredCapabilities,
    ): bool {
        $capabilities = $this->stringList($configuration['capabilities'] ?? null);

        return ($configuration['enabled'] ?? true) === true
            && $capabilities !== []
            && array_diff($requiredCapabilities, $capabilities) === []
            && $this->routingListsAreValid($configuration);
    }

    /** @param array<string, mixed> $configuration */
    private function routingListsAreValid(array $configuration): bool
    {
        return $this->stringList($configuration['allowed_market_country_codes'] ?? null) !== []
            && $this->stringList($configuration['residency_country_codes'] ?? null) !== [];
    }

    private function supportsChannel(AiReleaseCandidateSet $set, string $channel): bool
    {
        $channels = $this->stringList($set->agentConfiguration['channels'] ?? null);

        return in_array('*', $channels, true) || in_array($channel, $channels, true);
    }

    private function supportsMarket(AiReleaseCandidateSet $set, string $market): bool
    {
        foreach ([
            $set->providerConfiguration,
            $set->modelConfiguration,
            $set->policyRules,
        ] as $configuration) {
            foreach (['allowed_market_country_codes', 'residency_country_codes'] as $key) {
                $countries = $this->stringList($configuration[$key] ?? null);
                if (! in_array('*', $countries, true) && ! in_array($market, $countries, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }
        $items = array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item) && trim($item) !== '',
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
