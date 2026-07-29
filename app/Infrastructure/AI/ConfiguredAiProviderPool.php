<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\Ai\Contracts\AiProvider;
use App\Application\Ai\Contracts\AiProviderPool;
use App\Exceptions\Ai\AiExecutionDeniedException;

final readonly class ConfiguredAiProviderPool implements AiProviderPool
{
    /** @param array<string, AiProvider> $providers */
    public function __construct(
        private array $providers,
    ) {}

    public function resolve(string $adapterKey): AiProvider
    {
        $provider = $this->providers[$adapterKey] ?? null;
        if (! $provider instanceof AiProvider || $provider instanceof DisabledAiProvider) {
            throw new AiExecutionDeniedException('provider_adapter');
        }

        return $provider;
    }
}
