<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exceptions;

use RuntimeException;

/**
 * OrderCannotBeCancelledException - Domain Exception for Cancellation Invariant Violation
 *
 * Thrown when attempting to cancel an order that has progressed to a terminal state.
 */
final class OrderCannotBeCancelledException extends RuntimeException
{
    public function __construct(string $message = 'Order cannot be cancelled')
    {
        parent::__construct($message);
    }
}
