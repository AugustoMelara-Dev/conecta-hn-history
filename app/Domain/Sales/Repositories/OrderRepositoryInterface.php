<?php

declare(strict_types=1);

namespace App\Domain\Sales\Repositories;

/**
 * OrderRepositoryInterface - Port for Order Persistence
 *
 * This interface defines the contract for Order aggregate persistence.
 * Handlers use this interface; Infrastructure provides implementations.
 */
interface OrderRepositoryInterface
{
    /**
     * Find an order by primary identifier.
     *
     * @return object|null Order entity or null
     */
    public function findById(int $id): ?object;

    /**
     * Create a new order with its items.
     *
     * @param array<string, mixed> $orderAttributes
     * @param array<array<string, mixed>> $items
     * @return object The created order
     */
    public function createWithItems(array $orderAttributes, array $items): object;

    /**
     * Generate a unique order number for a tenant.
     *
     * @param int $tenantId
     * @return string
     */
    public function generateOrderNumber(int $tenantId): string;

    /**
     * Update an order's coupon reference.
     *
     * @param int $orderId
     * @param int $couponId
     */
    public function linkCoupon(int $orderId, int $couponId): void;
}
