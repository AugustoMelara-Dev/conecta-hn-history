<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Marketing;

use App\ValueObjects\Money;
use Illuminate\Contracts\Support\Arrayable;

/**
 * CouponResult - Type-Safe Coupon Validation Response
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Eliminate Primitive Obsession                 ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  PROBLEM: Using floats for financial values                             ║
 * ║    float $discount = 10.50; // Floating point errors in calculations   ║
 * ║                                                                          ║
 * ║  SOLUTION: Use Money Value Object for all financial fields             ║
 * ║    Money $discount; // Integer cents, no precision loss                ║
 * ║                                                                          ║
 * ║  REJECTION REASONS via Enum (not magic strings):                        ║
 * ║    CouponRejection::EXPIRED, ::USAGE_LIMIT, ::MIN_SPEND                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class CouponResult implements Arrayable, \JsonSerializable
{
    private function __construct(
        public bool $isValid,
        public ?CouponRejection $rejection = null,
        public ?string $message = null,
        public ?Money $discount = null,
        public ?Money $newTotal = null,
        public ?string $couponCode = null,
        public ?CouponType $couponType = null,
        public ?int $couponValue = null,
        public ?Money $minSpend = null,
        public ?Money $remaining = null,
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // FACTORY METHODS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Create a successful validation result.
     */
    public static function success(
        Money $discount,
        Money $newTotal,
        string $couponCode,
        CouponType $couponType,
        int $couponValue,
    ): self {
        return new self(
            isValid: true,
            discount: $discount,
            newTotal: $newTotal,
            couponCode: $couponCode,
            couponType: $couponType,
            couponValue: $couponValue,
        );
    }

    /**
     * Create a rejection result with typed reason.
     */
    public static function rejected(CouponRejection $reason, ?string $message = null): self
    {
        return new self(
            isValid: false,
            rejection: $reason,
            message: $message ?? $reason->message(),
        );
    }

    /**
     * Create a minimum spend rejection with context.
     */
    public static function minSpendNotMet(Money $minSpend, Money $remaining): self
    {
        return new self(
            isValid: false,
            rejection: CouponRejection::MIN_SPEND_NOT_MET,
            message: "Mínimo de compra no alcanzado",
            minSpend: $minSpend,
            remaining: $remaining,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SERIALIZATION
    // ═══════════════════════════════════════════════════════════════════════

    public function toArray(): array
    {
        if ($this->isValid) {
            return [
                'valid' => true,
                'discount' => [
                    'cents' => $this->discount->cents(),
                    'formatted' => $this->discount->formatted(),
                ],
                'new_total' => [
                    'cents' => $this->newTotal->cents(),
                    'formatted' => $this->newTotal->formatted(),
                ],
                'coupon_code' => $this->couponCode,
                'coupon_type' => $this->couponType->value,
                'coupon_value' => $this->couponValue,
            ];
        }

        $result = [
            'valid' => false,
            'error_code' => $this->rejection->value,
            'message' => $this->message,
        ];

        if ($this->minSpend !== null) {
            $result['min_spend'] = [
                'cents' => $this->minSpend->cents(),
                'formatted' => $this->minSpend->formatted(),
            ];
            $result['remaining'] = [
                'cents' => $this->remaining->cents(),
                'formatted' => $this->remaining->formatted(),
            ];
        }

        return $result;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
