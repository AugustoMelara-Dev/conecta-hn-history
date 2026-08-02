<?php

declare(strict_types=1);

namespace App\Application\Ai\Workforce\Contracts;

use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Ai\Registry\AiRuntimeSelection;

interface WorkforceRegistryAuthority
{
    public function resolve(AiRegistryQuery $query): AiRuntimeSelection;

    public function resolvePinned(AiRegistryQuery $query, string $agentVersionId): AiRuntimeSelection;
}
