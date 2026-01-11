<?php

declare(strict_types=1);

namespace App\Application\Interactions\Results;

/**
 * RecordInteractionResult - Immutable Result DTO for Analytics
 *
 * DOMAIN: Interactions (Analytics)
 * TYPE: Pure DTO
 * INVARIANT: Zero coupling to HTTP Request objects.
 */
final readonly class RecordInteractionResult
{
    /**
     * @param int $tenantId
     * @param string $type
     * @param int|null $productId
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param string|null $referrer
     * @param string|null $url
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public int $tenantId,
        public string $type,
        public ?int $productId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $referrer,
        public ?string $url,
        public ?array $metadata
    ) {}
}
