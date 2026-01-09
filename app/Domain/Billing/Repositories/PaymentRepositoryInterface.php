<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Models\Payment;

/**
 * PaymentRepositoryInterface - Port for Payment Persistence
 *
 * This interface defines the contract for Payment entity persistence.
 * Applications use this interface; Infrastructure provides implementations.
 */
interface PaymentRepositoryInterface
{
    /**
     * Find a payment by primary identifier.
     */
    public function findById(int $id): ?Payment;

    /**
     * Find payments by tenant ID.
     *
     * @return Payment[]
     */
    public function findByTenantId(int $tenantId): array;

    /**
     * Create a new payment record.
     *
     * @param array<string, mixed> $attributes
     * @return Payment
     */
    public function create(array $attributes): Payment;

    /**
     * Persist a payment entity.
     */
    public function save(Payment $payment): void;
}
