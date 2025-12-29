<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * CouponFactory - Test Factory for Coupon Model
 *
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('???###')),
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement([
                Coupon::TYPE_PERCENT,
                Coupon::TYPE_FIXED,
                Coupon::TYPE_FREE_SHIPPING,
            ]),
            'value' => $this->faker->randomFloat(2, 5, 50),
            'max_discount' => null,
            'min_spend' => null,
            'usage_limit' => null,
            'usage_count' => 0,
            'max_uses_per_user' => 0, // Guest-friendly: 0 = no per-user limit
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
            'description' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the coupon is a percentage discount.
     */
    public function percent(float $value = 10.0): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Coupon::TYPE_PERCENT,
            'value' => $value,
        ]);
    }

    /**
     * Indicate that the coupon is a fixed amount discount.
     */
    public function fixed(float $value = 25.0): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Coupon::TYPE_FIXED,
            'value' => $value,
        ]);
    }

    /**
     * Indicate that the coupon is free shipping.
     */
    public function freeShipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Coupon::TYPE_FREE_SHIPPING,
            'value' => 0,
        ]);
    }

    /**
     * Indicate that the coupon is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the coupon is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the coupon is scheduled for the future.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->addDay(),
        ]);
    }

    /**
     * Indicate that the coupon is depleted.
     */
    public function depleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_limit' => 10,
            'usage_count' => 10,
        ]);
    }

    /**
     * Set a minimum spend requirement.
     */
    public function minSpend(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'min_spend' => $amount,
        ]);
    }

    /**
     * Set a maximum discount cap (for percentage coupons).
     */
    public function maxDiscount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'max_discount' => $amount,
        ]);
    }

    /**
     * Indicate that the coupon has per-user limits (requires login).
     *
     * @param  int  $limit  Maximum uses per user (default 1)
     */
    public function perUserLimited(int $limit = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'max_uses_per_user' => $limit,
        ]);
    }
}
