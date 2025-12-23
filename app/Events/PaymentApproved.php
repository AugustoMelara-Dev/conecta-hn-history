<?php

namespace App\Events;

use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PaymentApproved - Immutable Domain Event Contract
 *
 * ARCHITECTURAL PATTERN: Event-Driven Architecture with DTO Contracts
 *
 * This event is an IMMUTABLE DATA TRANSFER OBJECT:
 * - All properties are public readonly (PHP 8.1+)
 * - Uses primitive types, not Eloquent models (prevents state mutation)
 * - Uses CarbonImmutable, not Carbon (prevents date mutation)
 *
 * The listener (UpdateTenantSubscription) is responsible for:
 * - Fetching the models from the database with locks
 * - Validating state before processing
 * - Applying transactional changes
 *
 * @property int $tenantId The tenant receiving subscription extension
 * @property int $paymentId The approved payment record
 * @property CarbonImmutable $approvedAt Immutable timestamp of approval
 * @property int $reviewerId The admin who approved the payment
 * @property int $daysToAdd Number of subscription days to extend
 */
class PaymentApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $paymentId,
        public readonly CarbonImmutable $approvedAt,
        public readonly int $reviewerId,
        public readonly int $daysToAdd = 30
    ) {}

    /**
     * Factory method for cleaner instantiation.
     */
    public static function fromPayment(
        int $tenantId,
        int $paymentId,
        int $reviewerId,
        int $daysToAdd = 30
    ): self {
        return new self(
            tenantId: $tenantId,
            paymentId: $paymentId,
            approvedAt: CarbonImmutable::now(),
            reviewerId: $reviewerId,
            daysToAdd: $daysToAdd
        );
    }
}
