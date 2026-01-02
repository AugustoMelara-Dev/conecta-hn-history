<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * InvalidOrderTransitionException - FSM Violation Error
 *
 * Thrown when an illegal state transition is attempted on an Order.
 * This enforces the Finite State Machine topology.
 */
final class InvalidOrderTransitionException extends DomainException
{
    public function __construct(
        private readonly string $currentStatus,
        private readonly string $attemptedStatus,
        private readonly array $allowedTransitions = [],
        ?\Throwable $previous = null,
    ) {
        $allowed = empty($allowedTransitions)
            ? 'none'
            : implode(', ', $allowedTransitions);

        parent::__construct(
            "Invalid order transition: Cannot move from '{$currentStatus}' to '{$attemptedStatus}'. " .
            "Allowed transitions from '{$currentStatus}': [{$allowed}]",
            0,
            $previous,
        );

        // Security: validate context doesn't contain sensitive data
        $this->validateContextSafety($allowedTransitions);
    }

    public function context(): array
    {
        return [
            'current_status' => $this->currentStatus,
            'attempted_status' => $this->attemptedStatus,
            'allowed_transitions' => $this->allowedTransitions,
        ];
    }

    public function getHttpStatusCode(): int
    {
        return 422; // Unprocessable Entity
    }

    public function getErrorCode(): string
    {
        return 'INVALID_ORDER_TRANSITION';
    }

    public function currentStatus(): string
    {
        return $this->currentStatus;
    }

    public function attemptedStatus(): string
    {
        return $this->attemptedStatus;
    }

    public function allowedTransitions(): array
    {
        return $this->allowedTransitions;
    }
}
