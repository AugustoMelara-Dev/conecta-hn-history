<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

/**
 * Fencing token for one durable webhook-processing lease.
 */
final readonly class WebhookEventClaim
{
    public function __construct(
        public string $provider,
        public string $externalEventId,
        public string $processingToken,
    ) {}
}
