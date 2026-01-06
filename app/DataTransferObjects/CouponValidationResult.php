<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * CouponValidationResult - Strongly Typed Validation Response
 *
 * PURPOSE: Replace magic arrays with type-safe, IDE-friendly objects.
 *
 * ARCHITECTURAL BENEFITS:
 * - Compile-time type checking
 * - IDE autocompletion for all properties
 * - Immutable design prevents accidental mutation
 * - Factory methods enforce consistent construction
 *
 * USAGE:
 *   $result = $coupon->validate(500.00);
 *   if ($result->isValid) {
 *       echo "Discount: " . $result->discountAmount;
 *   } else {
 *       echo "Error: " . $result->error;
 *   }
 */
readonly class CouponValidationResult
{
    private function __construct(
        public bool $isValid,
        public ?string $error = null,
        public ?string $errorCode = null,
        public float $discountAmount = 0.0,
        public float $newTotal = 0.0,
        public ?string $couponCode = null,
        public ?string $couponType = null,
        public ?float $couponValue = null,
        public ?float $minSpend = null,
        public ?float $remaining = null,
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // FACTORY METHODS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Create a successful validation result.
     */
    public static function success(
        float $discountAmount,
        float $newTotal,
        string $couponCode,
        string $couponType,
        float $couponValue,
    ): self {
        return new self(
            isValid: true,
            discountAmount: round($discountAmount, 2),
            newTotal: round($newTotal, 2),
            couponCode: $couponCode,
            couponType: $couponType,
            couponValue: $couponValue,
        );
    }

    /**
     * Create an error validation result.
     */
    public static function error(string $message, string $code): self
    {
        return new self(
            isValid: false,
            error: $message,
            errorCode: $code,
        );
    }

    /**
     * Create a minimum spend error with additional context.
     */
    public static function minSpendError(
        string $message,
        float $minSpend,
        float $remaining,
    ): self {
        return new self(
            isValid: false,
            error: $message,
            errorCode: 'MIN_SPEND',
            minSpend: $minSpend,
            remaining: $remaining,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CONVENIENCE METHODS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Convert to array for JSON responses (backwards compatibility).
     */
    public function toArray(): array
    {
        if ($this->isValid) {
            return [
                'valid' => true,
                'discount_amount' => $this->discountAmount,
                'new_total' => $this->newTotal,
                'coupon_code' => $this->couponCode,
                'coupon_type' => $this->couponType,
                'coupon_value' => $this->couponValue,
            ];
        }

        $result = [
            'valid' => false,
            'error' => $this->error,
            'error_code' => $this->errorCode,
        ];

        if ($this->minSpend !== null) {
            $result['min_spend'] = $this->minSpend;
            $result['remaining'] = $this->remaining;
        }

        return $result;
    }
}
