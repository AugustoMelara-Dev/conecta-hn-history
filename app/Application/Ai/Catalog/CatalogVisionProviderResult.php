<?php

declare(strict_types=1);

namespace App\Application\Ai\Catalog;

final readonly class CatalogVisionProviderResult
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public array $payload,
        public string $provider,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public float $costUsd,
        public int $durationMs,
        public array $rawResponse,
    ) {}
}
