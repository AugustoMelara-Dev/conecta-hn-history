<?php

declare(strict_types=1);

namespace App\Domain\Billing\Mappers;

use App\Domain\Billing\Enums\CanonicalPaymentState;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use UnexpectedValueException;

/**
 * Explicit compatibility boundary for persisted payment-state vocabularies.
 *
 * It maps values for decisions only; callers must retain their source value.
 */
final class CanonicalPaymentStateMapper
{
    public static function fromManualEvidence(PaymentStatus|string $status): CanonicalPaymentState
    {
        return match (self::valueOf($status)) {
            PaymentStatus::PENDING->value => CanonicalPaymentState::Pending,
            PaymentStatus::REVIEW->value => CanonicalPaymentState::RequiresAction,
            PaymentStatus::PAID->value, 'approved' => CanonicalPaymentState::Succeeded,
            PaymentStatus::REJECTED->value => CanonicalPaymentState::Failed,
            default => throw new UnexpectedValueException('Unknown manual payment evidence status: '.self::valueOf($status)),
        };
    }

    public static function fromSubscriptionIntent(string $status): CanonicalPaymentState
    {
        return match ($status) {
            'PENDING' => CanonicalPaymentState::Pending,
            'PROCESSING' => CanonicalPaymentState::Processing,
            'COMPLETED' => CanonicalPaymentState::Succeeded,
            'FAILED' => CanonicalPaymentState::Failed,
            default => throw new UnexpectedValueException("Unknown subscription intent status: {$status}"),
        };
    }

    public static function fromTransactionStatus(TransactionStatus|string $status): CanonicalPaymentState
    {
        return match (self::valueOf($status)) {
            TransactionStatus::PENDING->value => CanonicalPaymentState::Pending,
            TransactionStatus::PROCESSING->value => CanonicalPaymentState::Processing,
            TransactionStatus::REQUIRES_ACTION->value => CanonicalPaymentState::RequiresAction,
            TransactionStatus::COMPLETED->value => CanonicalPaymentState::Succeeded,
            TransactionStatus::FAILED->value => CanonicalPaymentState::Failed,
            TransactionStatus::CANCELLED->value => CanonicalPaymentState::Cancelled,
            TransactionStatus::REFUNDED->value => CanonicalPaymentState::Refunded,
            TransactionStatus::PARTIALLY_REFUNDED->value => CanonicalPaymentState::PartiallyRefunded,
            TransactionStatus::DISPUTED->value => CanonicalPaymentState::Disputed,
            default => throw new UnexpectedValueException('Unknown transaction status: '.self::valueOf($status)),
        };
    }

    public static function fromOrderPaymentStatus(string $status): CanonicalPaymentState
    {
        return match ($status) {
            'pending' => CanonicalPaymentState::Pending,
            'paid' => CanonicalPaymentState::Succeeded,
            'failed' => CanonicalPaymentState::Failed,
            'refunded' => CanonicalPaymentState::Refunded,
            default => throw new UnexpectedValueException("Unknown order payment status: {$status}"),
        };
    }

    private static function valueOf(PaymentStatus|TransactionStatus|string $status): string
    {
        return $status instanceof PaymentStatus || $status instanceof TransactionStatus
            ? $status->value
            : $status;
    }
}
