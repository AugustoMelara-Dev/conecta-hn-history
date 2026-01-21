<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entities;

use DateTimeImmutable;

/**
 * SubscriptionIntent - Financial Write-Ahead Log Entry
 *
 * Represents the intent to charge a customer, persisted BEFORE
 * calling the external gateway.
 */
final readonly class SubscriptionIntent
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_PROCESSING = 'PROCESSING';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_FAILED = 'FAILED';

    public function __construct(
        public string $id,
        public string $tenantId,
        public string $planIdentifier,
        public string $gatewayProvider,
        public string $status,
        public ?string $gatewayTransactionId,
        public ?string $errorReason,
        public DateTimeImmutable $createdAt,
    ) {}
}
