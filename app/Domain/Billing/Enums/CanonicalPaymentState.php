<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

/**
 * Payment semantics shared at billing decision boundaries.
 *
 * This enum deliberately does not replace legacy persisted vocabularies.
 */
enum CanonicalPaymentState: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case RequiresAction = 'requires_action';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Disputed = 'disputed';

    public function isSuccessful(): bool
    {
        return $this === self::Succeeded;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded,
            self::Failed,
            self::Cancelled,
            self::Refunded,
            self::Disputed => true,
            self::Pending,
            self::Processing,
            self::RequiresAction,
            self::PartiallyRefunded => false,
        };
    }

    public function canGrantEntitlement(): bool
    {
        return $this === self::Succeeded;
    }

    public function isInProgress(): bool
    {
        return match ($this) {
            self::Pending, self::Processing, self::RequiresAction => true,
            self::Succeeded,
            self::Failed,
            self::Cancelled,
            self::Refunded,
            self::PartiallyRefunded,
            self::Disputed => false,
        };
    }
}
