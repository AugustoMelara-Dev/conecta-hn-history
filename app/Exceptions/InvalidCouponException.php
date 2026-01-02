<?php

declare(strict_types=1);

namespace App\Exceptions;

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
 *       return response()->json($e->toArray(), $e->getHttpStatusCode());
 *   }
 */
final class InvalidCouponException extends DomainException
{
    public function __construct(
        string $message,
        private readonly string $errorCodeValue = 'INVALID_COUPON',
        private readonly array $contextData = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->validateContextSafety($contextData);
    }

    public function context(): array
    {
        return [
            'coupon_error' => $this->errorCodeValue,
            ...$this->contextData,
        ];
    }

    public function getHttpStatusCode(): int
    {
        return match ($this->errorCodeValue) {
            'NOT_FOUND' => 404,
            'DEPLETED', 'EXPIRED', 'INACTIVE' => 410, // Gone
            default => 422, // Unprocessable Entity
        };
    }

    public function getErrorCode(): string
    {
        return $this->errorCodeValue;
    }
}
