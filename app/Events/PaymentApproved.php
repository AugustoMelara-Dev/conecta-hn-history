<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Shared\ValueObjects\TenantId;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PaymentApproved - Subscription payment approval event.
 */
final class PaymentApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        private TenantId $tenantId,
        public string $paymentId,
        public ?string $reviewerId,
        public int $daysToAdd,
    ) {}

    public static function fromPayment(
        string $tenantId,
        string $paymentId,
        ?string $reviewerId,
        int $daysToAdd = 30,
    ): self {
        return new self(
            tenantId: TenantId::fromString($tenantId),
            paymentId: $paymentId,
            reviewerId: $reviewerId,
            daysToAdd: $daysToAdd,
        );
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }
}
