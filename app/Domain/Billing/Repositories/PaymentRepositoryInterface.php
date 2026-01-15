<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Domain\Billing\Entities\PaymentEntity;

/**
 * PaymentRepositoryInterface - Port for Payment Persistence
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HEXAGONAL COMPLIANT: No Eloquent or Infrastructure dependencies.       ║
 * ║  DOMAIN PURE: Uses PaymentEntity, not Eloquent Model.                   ║
 * ║  CLEAN CONTRACT: No legacy array methods permitted.                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface PaymentRepositoryInterface
{
    /**
     * Find a payment by primary identifier.
     * @throws \App\Domain\Billing\Exceptions\PaymentNotFoundException
     */
    public function findById(string $id): ?PaymentEntity;

    /**
     * Find payments by tenant ID.
     *
     * @return PaymentEntity[]
     */
    public function findByTenantId(string $tenantId): array;

    /**
     * Persist a payment entity.
     * Handles both creation and updates (Upsert).
     */
    public function save(PaymentEntity $payment): void;
}
