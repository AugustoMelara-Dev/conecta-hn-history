<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * PaymentReported - Domain Event for Manual Payment Submission
 *
 * Implements ShouldDispatchAfterCommit for Transactional Outbox safety.
 */
final readonly class PaymentReported implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $paymentId,
        public int $tenantId,
        public float $amount,
        public string $reference,
    ) {}

    /**
     * Serialize to safe payload for Outbox storage.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'tenant_id' => $this->tenantId,
            'amount' => $this->amount,
            'reference' => $this->reference,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
