<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class SubscriptionPaymentApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public string $tenantId,
        public string $paymentId,
        public string $reviewerId,
        public int $daysToAdd = 30,
    ) {
        if ($this->daysToAdd < 1) {
            throw new \InvalidArgumentException('Subscription extension days must be positive.');
        }
    }

    public static function fromPayment(
        string $tenantId,
        int|string $paymentId,
        int|string $reviewerId,
        int $daysToAdd = 30,
    ): self {
        return new self(
            tenantId: (string) $tenantId,
            paymentId: (string) $paymentId,
            reviewerId: (string) $reviewerId,
            daysToAdd: $daysToAdd,
        );
    }
}
