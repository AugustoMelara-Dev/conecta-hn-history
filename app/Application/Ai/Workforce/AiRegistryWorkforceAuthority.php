<?php

declare(strict_types=1);

namespace App\Application\Ai\Workforce;

use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Ai\Registry\AiRegistryResolver;
use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Application\Ai\Workforce\Contracts\WorkforceRegistryAuthority;

final readonly class AiRegistryWorkforceAuthority implements WorkforceRegistryAuthority
{
    public function __construct(
        private AiRegistryResolver $registry,
    ) {}

    public function resolve(AiRegistryQuery $query): AiRuntimeSelection
    {
        return $this->registry->resolve($query);
    }

    public function resolvePinned(AiRegistryQuery $query, string $agentVersionId): AiRuntimeSelection
    {
        return $this->registry->resolvePinned($query, $agentVersionId);
    }
}
