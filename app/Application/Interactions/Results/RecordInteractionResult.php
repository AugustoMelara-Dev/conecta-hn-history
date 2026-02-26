<?php

declare(strict_types=1);

namespace App\Application\Interactions\Results;

/**
 * RecordInteractionResult - Immutable Result
 *
 * DOMAIN: Interactions (Analytics)
 * AXIOM: Zero HTTP Leakage.
 */
final readonly class RecordInteractionResult
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $tenantId,
        public string $type,
        public ?string $productId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $referrer,
        public ?string $url,
        public ?array $metadata
    ) {}
}
