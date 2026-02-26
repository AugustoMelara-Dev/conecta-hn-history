<?php

declare(strict_types=1);

namespace App\Application\Interactions\DTOs;

/**
 * InteractionData - Pure Application Layer DTO
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURIFIED: No framework dependencies. HTTP mapping lives in Controller.║
 * ║  IMMUTABLE: final readonly — safe for queue serialization.             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class InteractionData
{
    public function __construct(
        public string $tenantId,
        public string $type,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $sessionId,
        public ?string $productId = null,
        public ?array $metadata = null,
    ) {}
}
