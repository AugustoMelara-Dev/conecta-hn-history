<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * OrderFactory - Test Data Generator for Orders
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DOMAIN INTEGRITY: Produces valid entities that respect business rules  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  1. Total is NEVER negative (uses max(0, calculation))                  ║
 * ║  2. Discount cannot exceed subtotal + tax                               ║
 * ║  3. All monetary values are valid decimals                              ║
 * ║  4. PII fields are properly formatted for encryption                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 100, 2000);
        $taxRate = 0.15; // Honduras ISV
        $taxAmount = round($subtotal * $taxRate, 2);

        // INTEGRITY FIX: Discount cannot exceed subtotal + tax
        $maxDiscount = $subtotal + $taxAmount;
        $discountAmount = $this->faker->randomFloat(2, 0, min(100, $maxDiscount * 0.3));

        // INTEGRITY FIX: Total is NEVER negative
        $total = max(0, $subtotal + $taxAmount - $discountAmount);

        return [
            'tenant_id' => Tenant::factory(),
            'order_number' => '#' . $this->faker->unique()->numberBetween(1001, 99999),
            // PII fields: Generate valid format (model handles encryption)
            'customer_name' => $this->faker->name(),
            'customer_phone' => $this->generateHondurasPhone(),
            'customer_email' => $this->faker->unique()->safeEmail(),
            'customer_address' => $this->faker->address(),
            'customer_notes' => $this->faker->optional(0.3)->sentence(),
            // Monetary values (always valid decimals)
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'delivery_fee' => $this->faker->randomFloat(2, 0, 50),
            'discount_amount' => $discountAmount,
            'total' => $total,
            // Enums and status
            'payment_method' => $this->faker->randomElement(PaymentMethod::cases()),
            'delivery_type' => $this->faker->randomElement(['delivery', 'pickup']),
            'status' => Order::STATUS_PENDING,
            'tracking_uuid' => Str::uuid()->toString(),
        ];
    }

    /**
     * Generate a valid Honduras phone number format.
     */
    private function generateHondurasPhone(): string
    {
        // Honduras mobile numbers start with 9, 8, or 3
        $prefix = $this->faker->randomElement(['9', '8', '3']);
        $number = $this->faker->numerify($prefix . '###-####');

        return $number;
    }

    /**
     * Set a specific status.
     */
    public function withStatus(string $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * Set as pending.
     */
    public function pending(): static
    {
        return $this->withStatus(Order::STATUS_PENDING);
    }

    /**
     * Set as confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Set as processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PROCESSING,
            'confirmed_at' => now()->subMinutes(30),
            'processing_started_at' => now(),
        ]);
    }

    /**
     * Set as ready.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_READY,
            'confirmed_at' => now()->subHours(1),
            'processing_started_at' => now()->subMinutes(30),
            'ready_at' => now(),
        ]);
    }

    /**
     * Set as delivered.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_DELIVERED,
            'confirmed_at' => now()->subHours(2),
            'processing_started_at' => now()->subHours(1),
            'ready_at' => now()->subMinutes(30),
            'delivered_at' => now(),
        ]);
    }

    /**
     * Set as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $this->faker->sentence(),
        ]);
    }

    /**
     * For a specific tenant.
     */
    public function forTenant(Tenant|int $tenant): static
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Create an order with specific total (for testing edge cases).
     */
    public function withTotal(float $total): static
    {
        return $this->state(fn (array $attributes) => [
            'subtotal' => $total,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => max(0, $total),
        ]);
    }
}
