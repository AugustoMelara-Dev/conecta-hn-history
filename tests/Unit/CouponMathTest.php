<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Coupon;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * CouponMathTest - Financial Logic Unit Tests
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PRECISION TESTING: Verifies no floating-point errors in calculations  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * These tests run WITHOUT database connections (pure unit tests).
 * They verify the mathematical precision of discount calculations.
 *
 * @covers \App\Models\Coupon::calculateDiscount
 * @covers \App\Models\Coupon::calculateDiscountUnsafe
 * @covers \App\Models\Coupon::calculatePercentDiscount
 * @covers \App\Models\Coupon::calculateFixedDiscount
 */
class CouponMathTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════════════════════
    // PRECISION ROUNDING TESTS
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies that percentage discounts are rounded to exactly 2 decimal places.
     * 
     * SCENARIO: 33.333% of $100 = $33.333... 
     * EXPECTED: Must return 33.33, not 33.33300001 (which breaks payment gateways)
     */
    public function it_rounds_percentage_discounts_to_two_decimals(): void
    {
        $coupon = $this->createCouponInstance([
            'type' => Coupon::TYPE_PERCENT,
            'value' => 33.333,
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
            'max_discount' => null,
        ]);

        $discount = $coupon->calculateDiscountUnsafe(100.00);

        // Strict type comparison - must be exactly 33.33
        $this->assertSame(33.33, $discount);
        $this->assertIsFloat($discount);
        
        // Verify string representation matches (no hidden precision errors)
        $this->assertSame('33.33', number_format($discount, 2, '.', ''));
    }

    /**
     * @test
     * Additional precision test with repeating decimal.
     * 
     * SCENARIO: 10% of $333.33 = $33.333...
     * EXPECTED: Must return 33.33
     */
    public function it_handles_repeating_decimals_correctly(): void
    {
        $coupon = $this->createCouponInstance([
            'type' => Coupon::TYPE_PERCENT,
            'value' => 10.00,
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
            'max_discount' => null,
        ]);

        $discount = $coupon->calculateDiscountUnsafe(333.33);

        $this->assertSame(33.33, $discount);
    }

    /**
     * @test
     * Verifies percentage discount respects max_discount cap.
     * 
     * SCENARIO: 50% of $200 = $100, but max_discount = $50
     * EXPECTED: Must return 50.00 (capped)
     */
    public function it_caps_percentage_discount_at_max_discount(): void
    {
        $coupon = $this->createCouponInstance([
            'type' => Coupon::TYPE_PERCENT,
            'value' => 50.00,
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
            'max_discount' => 50.00,
        ]);

        $discount = $coupon->calculateDiscountUnsafe(200.00);

        $this->assertSame(50.00, $discount);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // NEGATIVE TOTAL PREVENTION ("Subtotal Cap")
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * CRITICAL: Verifies that fixed discounts cannot exceed cart subtotal.
     * 
     * SCENARIO: L.500 coupon on L.100 cart
     * EXPECTED: Must return 100.00 (not 500.00 which would cause -400 total)
     * 
     * This test prevents the catastrophic business rule violation of
     * negative transaction totals.
     */
    public function it_caps_fixed_discount_at_subtotal(): void
    {
        $coupon = $this->createCouponInstance([
            'type' => Coupon::TYPE_FIXED,
            'value' => 500.00,
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
        ]);

        $discount = $coupon->calculateDiscountUnsafe(100.00);

        // CRITICAL ASSERTION: Discount must never exceed subtotal
        $this->assertSame(100.00, $discount);
        $this->assertLessThanOrEqual(100.00, $discount);
    }

    /**
     * @test
     * Verifies fixed discount works normally when under subtotal.
     */
    public function it_returns_full_fixed_discount_when_under_subtotal(): void
    {
        $coupon = $this->createCouponInstance([
            'type' => Coupon::TYPE_FIXED,
            'value' => 25.00,
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
        ]);

        $discount = $coupon->calculateDiscountUnsafe(100.00);

        $this->assertSame(25.00, $discount);
    }

    /**
     * @test
     * Edge case: Fixed discount exactly equals subtotal.
     */
    public function it_allows_fixed_discount_equal_to_subtotal(): void
    {
        $coupon = $this->createCouponInstance([
            'type' => Coupon::TYPE_FIXED,
            'value' => 100.00,
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
        ]);

        $discount = $coupon->calculateDiscountUnsafe(100.00);

        $this->assertSame(100.00, $discount);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // FREE SHIPPING PASSTHROUGH
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies free_shipping returns the exact shipping cost passed.
     * 
     * SCENARIO: Free shipping coupon, $55.50 shipping cost
     * EXPECTED: Must return 55.50 exactly (ignore coupon's nominal value)
     */
    public function it_returns_shipping_cost_for_free_shipping_type(): void
    {
        $coupon = $this->createCouponInstance([
            'type' => Coupon::TYPE_FREE_SHIPPING,
            'value' => 999.99, // Should be ignored
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
        ]);

        $discount = $coupon->calculateDiscountUnsafe(100.00, 55.50);

        $this->assertSame(55.50, $discount);
    }

    /**
     * @test
     * Verifies free_shipping with zero shipping returns zero.
     */
    public function it_returns_zero_for_free_shipping_with_no_shipping_cost(): void
    {
        $coupon = $this->createCouponInstance([
            'type' => Coupon::TYPE_FREE_SHIPPING,
            'value' => 100.00,
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
        ]);

        $discount = $coupon->calculateDiscountUnsafe(500.00, 0.00);

        $this->assertSame(0.00, $discount);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // VALIDATION BYPASS (calculateDiscountUnsafe vs calculateDiscount)
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Verifies that calculateDiscount returns 0 for inactive coupons.
     */
    public function it_returns_zero_for_inactive_coupons(): void
    {
        $coupon = $this->createCouponInstance([
            'type' => Coupon::TYPE_PERCENT,
            'value' => 50.00,
            'is_active' => false, // INACTIVE
            'starts_at' => null,
            'expires_at' => null,
        ]);

        $discount = $coupon->calculateDiscount(100.00);

        $this->assertSame(0.0, $discount);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // HELPER: Create in-memory Coupon instance
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Create a Coupon model instance in memory (no database).
     *
     * @param array<string, mixed> $attributes
     * @return Coupon
     */
    private function createCouponInstance(array $attributes): Coupon
    {
        $coupon = new Coupon();
        
        foreach ($attributes as $key => $value) {
            $coupon->{$key} = $value;
        }

        return $coupon;
    }
}
