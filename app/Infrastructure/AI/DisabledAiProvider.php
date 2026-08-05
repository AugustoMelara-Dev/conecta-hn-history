<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\Ai\Contracts\AiProvider;
use App\Application\Ai\DTOs\AiProviderRequest;
use App\Application\Ai\DTOs\AiProviderResult;
use App\Exceptions\Ai\AiProviderUnavailableException;

final class DisabledAiProvider implements AiProvider
{
    public function name(): string
    {
        return 'disabled';
    }

    public function generate(AiProviderRequest $request): AiProviderResult
    {
        throw new AiProviderUnavailableException('No AI provider is configured.');
    }
}
