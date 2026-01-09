<?php

declare(strict_types=1);

namespace App\Application\Interactions\Commands;

/**
 * RecordInteractionCommand - Immutable Interaction Event DTO
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TRANSPORT ONLY: No business logic - Handler decides deduplication.     ║
 * ║  HTTP AGNOSTIC: No Request dependency.                                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class RecordInteractionCommand
{
    public function __construct(
        /** Tenant context ID (pre-validated) */
        public int $tenantId,
        
        /** Unique visitor identifier (cookie or fingerprint) */
        public string $visitorId,
        
        /** Normalized interaction type (whatsapp_click, product_view) */
        public string $type,
        
        /** Optional product ID for product-specific interactions */
        public ?int $productId = null,
        
        /** Client IP address for audit */
        public ?string $ipAddress = null,
        
        /** Client User-Agent */
        public ?string $userAgent = null,
        
        /** Referrer URL */
        public ?string $referrer = null,
        
        /** Current page URL */
        public ?string $url = null,
        
        /** Additional metadata (extensible) */
        public array $metadata = [],
    ) {}
}
