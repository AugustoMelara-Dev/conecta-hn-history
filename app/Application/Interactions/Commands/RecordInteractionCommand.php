<?php

declare(strict_types=1);

namespace App\Application\Interactions\Commands;

use App\Application\Shared\Common\Interfaces\IdempotentCommandInterface;
use App\Application\Shared\Contracts\SensitiveDataInterface;

/**
 * RecordInteractionCommand - Immutable Interaction Event DTO
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TRANSPORT ONLY: No business logic - Handler decides deduplication.     ║
 * ║  HTTP AGNOSTIC: No Request dependency.                                  ║
 * ║  IDEMPOTENT: Deterministic key prevents phantom analytics.             ║
 * ║  GDPR: ipAddress & userAgent are PII — redacted in logs & Outbox.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class RecordInteractionCommand implements IdempotentCommandInterface, SensitiveDataInterface
{
    public function __construct(
        /** Tenant context ID (pre-validated) */
        public string $tenantId,

        /** Unique visitor identifier (cookie or fingerprint) */
        public string $visitorId,

        /** Normalized interaction type (whatsapp_click, product_view) */
        public string $type,

        /** Optional product ID for product-specific interactions */
        public ?string $productId = null,

        /** Client IP address for audit (PII — GDPR Art. 4) */
        #[\SensitiveParameter]
        public ?string $ipAddress = null,

        /** Client User-Agent (PII — GDPR Art. 4) */
        #[\SensitiveParameter]
        public ?string $userAgent = null,

        /** Referrer URL */
        public ?string $referrer = null,

        /** Current page URL */
        public ?string $url = null,

        /** Additional metadata (extensible) */
        public array $metadata = [],

        /** Unique Receipt ID for transactional idempotency */
        public ?string $idempotencyKey = null,

        /** Schema Version for Upcasting */
        public int $schemaVersion = 1,
    ) {}

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey ?: hash('xxh128', 'interaction_' . $this->tenantId . '_' . $this->visitorId . '_' . $this->type);
    }

    /**
     * {@inheritDoc}
     *
     * GDPR compliance: ipAddress and userAgent are Personally Identifiable
     * Information and must be redacted from logs, stack traces, and
     * Transactional Outbox payloads.
     */
    public function getSensitiveProperties(): array
    {
        return ['ipAddress', 'userAgent'];
    }
}
