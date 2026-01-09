<?php

declare(strict_types=1);

namespace App\Domain\Orders\Exceptions;

use RuntimeException;

/**
 * PdfGenerationException - Domain Exception for PDF Rendering Failures
 *
 * Wraps infrastructure errors with meaningful context.
 */
final class PdfGenerationException extends RuntimeException
{
    public function __construct(
        string $message = 'Failed to generate PDF receipt',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Create for order rendering failure.
     */
    public static function forOrder(int $orderId, ?\Throwable $previous = null): self
    {
        return new self("Failed to generate PDF receipt for order #{$orderId}", $previous);
    }
}
