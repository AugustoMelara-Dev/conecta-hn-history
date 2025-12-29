<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * InvalidOrderTransitionException - FSM Violation Error
 *
 * Thrown when an illegal state transition is attempted on an Order.
 * This enforces the Finite State Machine topology.
 */
class InvalidOrderTransitionException extends Exception
{
    public function __construct(
        public readonly string $currentStatus,
        public readonly string $attemptedStatus,
        public readonly array $allowedTransitions = []
    ) {
        $allowed = empty($allowedTransitions)
            ? 'none'
            : implode(', ', $allowedTransitions);

        parent::__construct(
            "Invalid order transition: Cannot move from '{$currentStatus}' to '{$attemptedStatus}'. ".
            "Allowed transitions from '{$currentStatus}': [{$allowed}]"
        );
    }

    /**
     * Get HTTP status code for this exception.
     */
    public function getHttpStatusCode(): int
    {
        return 422; // Unprocessable Entity
    }

    /**
     * Get error response array.
     */
    public function toArray(): array
    {
        return [
            'success' => false,
            'error_code' => 'INVALID_ORDER_TRANSITION',
            'message' => $this->getMessage(),
            'current_status' => $this->currentStatus,
            'attempted_status' => $this->attemptedStatus,
            'allowed_transitions' => $this->allowedTransitions,
        ];
    }
}
