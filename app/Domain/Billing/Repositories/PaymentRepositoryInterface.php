<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Domain\Billing\Entities\PaymentEntity;
use Illuminate\Support\Collection;

interface PaymentRepositoryInterface
{
    public function save(PaymentEntity $payment): void;
    
    /**
     * Update payment with Optimistic Concurrency Control.
     * Throws StaleObjectException if version mismatch.
     */
    public function updateOptimistically(PaymentEntity $payment): void;

    public function findById(string $id): ?PaymentEntity;

    /**
     * LEVEL 38: STRONG CONSISTENCY - Writer-Pinned Fetching
     * 
     * Forces the query to the PRIMARY (Writer) node, bypassing all read replicas.
     * MUST be used by async workers that require "Read-Your-Own-Writes" consistency.
     * 
     * @param string $id The Payment ID
     * @return PaymentEntity|null The Payment Entity or null if truly non-existent
     */
    public function findByIdOnWriteConnection(string $id): ?PaymentEntity;

    public function findByIdempotencyKey(string $tenantId, string $idempotencyKey): ?PaymentEntity;

    public function findPaymentsWithTemporaryProofs(int $limit = 50): Collection;
}
