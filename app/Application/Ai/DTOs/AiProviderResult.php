<?php

declare(strict_types=1);

namespace App\Application\Ai\DTOs;

use InvalidArgumentException;

final readonly class AiProviderResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public array $data,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public float $costUsd,
        public string $finishReason = 'stop',
    ) {
        if ($inputTokens < 0 || $outputTokens < 0 || ! is_finite($costUsd) || $costUsd < 0) {
            throw new InvalidArgumentException('AI provider usage is invalid.');
        }
    }
}
