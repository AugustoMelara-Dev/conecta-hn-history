<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

final readonly class AiReleaseCandidateSet
{
    /**
     * @param  array<string, mixed>  $agentConfiguration
     * @param  array<string, mixed>  $modelConfiguration
     * @param  array<string, mixed>  $providerConfiguration
     * @param  array<string, mixed>  $policyRules
     */
    public function __construct(
        public object $decision,
        public object $agent,
        public object $agentDefinition,
        public object $prompt,
        public object $promptDefinition,
        public object $model,
        public object $modelDefinition,
        public object $provider,
        public object $providerDefinition,
        public array $agentConfiguration,
        public array $modelConfiguration,
        public array $providerConfiguration,
        public array $policyRules,
    ) {}

    public function versionId(string $versionType): string
    {
        return match ($versionType) {
            'agent' => (string) $this->agent->id,
            'prompt' => (string) $this->prompt->id,
            'model' => (string) $this->model->id,
            'provider' => (string) $this->provider->id,
            default => '',
        };
    }
}
