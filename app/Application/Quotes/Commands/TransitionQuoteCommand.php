<?php

declare(strict_types=1);

namespace App\Application\Quotes\Commands;

final readonly class TransitionQuoteCommand
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public string $quoteId,
        public string $idempotencyKey,
        public string $targetStatus,
        public ?string $reason = null,
        public array $metadata = [],
    ) {}
}
