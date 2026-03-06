<?php

declare(strict_types=1);

namespace App\Application\Sales\Contracts;

use App\Models\Order;

/**
 * OrderStateServiceInterface - FSM Port for Order Lifecycle Management
 *
 * APPLICATION CONTRACT: Presentations layer must type-hint this interface,
 * not the concrete App\Services\OrderStateService.
 *
 * TODO (W-02): Replace App\Models\Order type-hint with a Domain OrderEntity
 *              once the Order aggregate is extracted from Eloquent.
 */
interface OrderStateServiceInterface
{
    /**
     * Transition an order to a new status.
     *
     * @throws \App\Exceptions\InvalidOrderTransitionException If transition is not allowed
     */
    public function transition(Order $order, string $newStatus): Order;

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
