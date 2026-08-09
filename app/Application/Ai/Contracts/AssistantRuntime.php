<?php

declare(strict_types=1);

namespace App\Application\Ai\Contracts;

use App\Application\Ai\DTOs\AssistantExecutionContext;

interface AssistantRuntime
{
    /** @param array<string, mixed> $input */
    public function execute(string $assistantKey, array $input, AssistantExecutionContext $context): AssistantResult;
}
