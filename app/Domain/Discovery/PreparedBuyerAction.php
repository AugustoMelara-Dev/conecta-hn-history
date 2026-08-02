<?php

declare(strict_types=1);

namespace App\Domain\Discovery;

final readonly class PreparedBuyerAction
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $lineage
     */
    public function __construct(
        public string $status,
        public ?string $reasonCode,
        public string $message,
        public array $payload,
        public array $lineage,
        public bool $requiresConfirmation,
        public bool $executionAllowed,
    ) {}
}
