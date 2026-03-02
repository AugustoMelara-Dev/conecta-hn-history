<?php

declare(strict_types=1);

namespace App\Application\Payments\Commands;

/**
 * HandlePaymentWebhookCommand - Normalized Input for External Payment Webhooks
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PROVIDER AGNOSTIC: Normalizes Stripe/PayPal/etc into unified format.   ║
 * ║  IDEMPOTENCY KEY: externalEventId allows duplicate detection.           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class HandlePaymentWebhookCommand
{
    public function __construct(
        /** Provider identifier (stripe, paypal, etc.) */
        public string $provider,

        /** Provider's unique event ID for idempotency */
        public string $externalEventId,

        /** Event type (payment_intent.succeeded, invoice.payment_failed, etc.) */
        public string $eventType,

        /** Full decoded JSON payload for audit trail */
        public array $payload,

        /** Mandatory tenant ID for audit trail isolation */
        public int|string $tenantId,
    ) {}
}
