<?php

declare(strict_types=1);

namespace App\Domain\Sales\Repositories;

use App\Domain\Sales\Entities\OrderEntity;

/**
 * OrderRepositoryInterface - Port for Order Persistence
 *
 * DOMAIN: Sales
 * TYPE: Repository Contract
 * INVARIANTS:
 * - Must operate on Domain Aggregates, not Arrays.
 * - Must return typed Domain Objects.
 */
interface OrderRepositoryInterface
{
    /**
     * Find an order by primary identifier.
     */
    public function findById(int $id): ?OrderEntity;

    /**
     * Persist a new or updated order aggregate.
     * Replaces createWithItems(array).
     */
    public function save(OrderEntity $order): OrderEntity;

    /**
     * Generate a unique order number for a tenant.
     */
    public function generateOrderNumber(int|string $tenantId): string;
}
