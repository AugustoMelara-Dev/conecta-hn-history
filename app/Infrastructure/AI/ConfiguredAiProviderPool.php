<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\Ai\Contracts\AiProvider;
use App\Application\Ai\Contracts\AiProviderPool;
use App\Exceptions\Ai\AiExecutionDeniedException;

final readonly class ConfiguredAiProviderPool implements AiProviderPool
{
    /** @param array<string, array<string, AiProvider>> $deployments */
    public function __construct(
        private array $deployments,
    ) {}

    public function resolve(string $adapterKey, string $secretReference): AiProvider
    {
        $references = $this->deployments[$adapterKey] ?? null;
        if (! is_array($references)) {
            throw new AiExecutionDeniedException('provider_adapter');
        }

        $provider = $references[$secretReference] ?? null;
        if (! $provider instanceof AiProvider || $provider instanceof DisabledAiProvider) {
            throw new AiExecutionDeniedException('provider_secret_reference');
        }

        return $provider;
    }
}
