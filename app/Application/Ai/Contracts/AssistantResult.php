<?php

declare(strict_types=1);

namespace App\Application\Ai\Contracts;

/**
 * AssistantResult - Resultado de ejecutar un AI Assistant.
 */
final readonly class AssistantResult
{
    public function __construct(
        public bool $success,
        public mixed $output,
        public float $costUsd = 0.0,
        public int $durationMs = 0,
        public ?string $errorMessage = null,
        public array $metadata = [],
    ) {}

    public static function success(mixed $output, float $cost = 0.0, int $durationMs = 0, array $metadata = []): self
    {
        return new self(true, $output, $cost, $durationMs, null, $metadata);
    }

    public static function failure(string $errorMessage, array $metadata = []): self
    {
        return new self(false, null, 0.0, 0, $errorMessage, $metadata);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'output' => $this->output,
            'cost_usd' => $this->costUsd,
            'duration_ms' => $this->durationMs,
            'error_message' => $this->errorMessage,
            'metadata' => $this->metadata,
        ];
    }
}


