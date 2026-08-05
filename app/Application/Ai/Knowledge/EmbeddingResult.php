<?php

declare(strict_types=1);

namespace App\Application\Ai\Knowledge;

final readonly class EmbeddingResult
{
    /** @param list<float> $vector */
    public function __construct(
        public array $vector,
        public string $model,
        public int $inputTokens,
    ) {}
}
