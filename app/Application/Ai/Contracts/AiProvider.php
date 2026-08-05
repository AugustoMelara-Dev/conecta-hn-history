<?php

declare(strict_types=1);

namespace App\Application\Ai\Contracts;

use App\Application\Ai\DTOs\AiProviderRequest;
use App\Application\Ai\DTOs\AiProviderResult;

interface AiProvider
{
    public function name(): string;

    public function generate(AiProviderRequest $request): AiProviderResult;
}
