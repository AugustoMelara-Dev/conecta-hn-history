<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * InvalidCouponException - Custom exception for coupon validation failures.
 *
 * USAGE:
 *   throw new InvalidCouponException('Cupón expirado', 'EXPIRED');
 *   throw new InvalidCouponException('Min no alcanzado', 'MIN_SPEND_NOT_MET', ['remaining' => 50.00]);
 *
 * CATCH:
 *   try {
 *       $coupon = $service->validate($code, $user, $total);
 *   } catch (InvalidCouponException $e) {
 *       return response()->json($e->toArray(), 422);
 *   }
 */
class InvalidCouponException extends Exception
{
    /**
     * @param  string  $message  Human-readable error message (Spanish for Honduras)
     * @param  string  $errorCode  Machine-readable error code for frontend handling
     * @param  array  $context  Additional context data (min_spend, remaining, etc.)
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly array $context = []
    ) {
        parent::__construct($message);
    }

    /**
     * Get the machine-readable error code.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get additional context data.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->context;
    }

    /**
     * Convert exception to API-friendly array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => false,
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
            'context' => $this->context,
        ];
    }

    /**
     * Get HTTP status code for this exception type.
     */
    public function getStatusCode(): int
    {
        return match ($this->errorCode) {
            'NOT_FOUND' => 404,
            'DEPLETED', 'EXPIRED', 'INACTIVE' => 410, // Gone
            default => 422, // Unprocessable Entity
        };
    }
}
