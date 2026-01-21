<?php

declare(strict_types=1);

namespace App\Domain\Interactions\DTOs;

/**
 * InteractionData - Immutable DTO for Interaction Tracking
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FRAMEWORK AGNOSTIC: Can be hydrated from Request or separate inputs    ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This DTO carries all necessary data to record an interaction,          ║
 * ║  decoupling the Domain layer from the HTTP Request object.              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class InteractionData
{
    /**
     * @param  int  $tenantId  ID of the tenant receiving the interaction
     * @param  string  $type  Type of interaction (view, whatsapp_click, call_click, etc.)
     * @param  string|null  $ipAddress  Visitor IP address
     * @param  string|null  $userAgent  Visitor User Agent
     * @param  string|null  $sessionId  Session ID for counting unique views
     * @param  int|null  $productId  Optional product ID context
     * @param  array|null  $metadata  Additional contextual data
     */
    public function __construct(
        public int $tenantId,
        public string $type,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $sessionId,
        public ?int $productId = null,
        public ?array $metadata = null,
    ) {}

    /**
     * Factory method removed to decouple from HTTP layer.
     * Instantiation should occur in Controller or FormRequest.
     */
}
