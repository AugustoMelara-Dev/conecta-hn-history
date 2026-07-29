<?php

declare(strict_types=1);

namespace App\Application\Ai\DTOs;

final readonly class AiProviderRequest
{
    public function __construct(
        public string $correlationId,
        public string $capability,
        public string $modelKey,
        public string $systemPrompt,
        public string $userInput,
        public int $timeoutMilliseconds,
        public int $maxOutputTokens,
        public string $outputSchema,
    ) {}
}
