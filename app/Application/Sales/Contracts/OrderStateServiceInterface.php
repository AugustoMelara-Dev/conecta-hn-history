<?php

declare(strict_types=1);

namespace App\Application\Sales\Contracts;

use App\Domain\Sales\Contracts\OrderStateful;

/**
 * OrderStateServiceInterface - FSM Port for Order Lifecycle Management
 *
 * APPLICATION CONTRACT: Presentations layer must type-hint this interface,
 * not the concrete App\Services\OrderStateService.
 *
 */
interface OrderStateServiceInterface
{
    /**
     * Transition an order to a new status.
     *
     * @throws \App\Exceptions\InvalidOrderTransitionException If transition is not allowed
     */
    public function transition(OrderStateful $order, string $newStatus): OrderStateful;

    /**
     * Validate that a transition is allowed by the FSM topology.
     *
     * @throws \App\Exceptions\InvalidOrderTransitionException
     */
    public function validateTransition(string $from, string $to): void;

    /**
     * Check if a transition is valid (without throwing).
     */
    public function canTransition(string $from, string $to): bool;

    /**
     * Get allowed transitions from a given status.
     *
     * @return array<string>
     */
    public function getAllowedTransitions(string $status): array;
}
