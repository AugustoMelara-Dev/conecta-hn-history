<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * OrderFactory - Test Data Generator for Orders
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DOMAIN INTEGRITY: Produces valid entities that respect business rules  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  1. Total is NEVER negative (uses max(0, calculation))                  ║
 * ║  2. Discount cannot exceed subtotal                                     ║
 * ║  3. All monetary values are valid decimals                              ║
 * ║  4. PII fields are properly formatted for encryption                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * ALIGNED WITH: 2025_01_02_000001_create_orders_table.php
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 100, 2000);
        $discount = $this->faker->randomFloat(2, 0, min(100, $subtotal * 0.3));
        $deliveryFee = $this->faker->randomFloat(2, 0, 50);

        // Total = subtotal - discount + delivery_fee
        $total = max(0, $subtotal - $discount + $deliveryFee);

        return [
            // ═══════════════════════════════════════════════════════════════════════
            // FOREIGN KEYS (Referential Integrity)
            // ═══════════════════════════════════════════════════════════════════════
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(), // Required by MySQL schema

            // ═══════════════════════════════════════════════════════════════════════
            // UNIQUE IDENTIFIERS
            // ═══════════════════════════════════════════════════════════════════════
            'order_number' => '#'.$this->faker->unique()->numberBetween(1001, 99999),
            'tracking_uuid' => $this->faker->uuid(), // Real-time tracking token

            // PII fields (as per migration)
            'customer_name' => $this->faker->name(),
            'customer_phone' => $this->generateHondurasPhone(),
            'customer_email' => $this->faker->unique()->safeEmail(),
            'delivery_address' => $this->faker->optional(0.7)->address(),
            'customer_notes' => $this->faker->optional(0.3)->sentence(),

            // Monetary values (matching migration columns)
            'subtotal' => $subtotal,
            'discount' => $discount,
            'delivery_fee' => $deliveryFee,
            'total' => $total,

            // Payment (as per migration)
            'payment_method' => $this->faker->randomElement(['cash', 'transfer', 'card', 'pending']),
            'payment_status' => 'pending',

            // Source (as per migration)
            'source' => $this->faker->randomElement(['whatsapp', 'web', 'direct']),

            // Status
            'status' => Order::STATUS_PENDING,
        ];
    }

    /**
     * Generate a valid Honduras phone number format.
     */
    private function generateHondurasPhone(): string
    {
        $prefix = $this->faker->randomElement(['9', '8', '3']);
        $number = $this->faker->numerify($prefix.'###-####');

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
     * Set as processing (preparing in migration).
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'preparing',
            'confirmed_at' => now()->subMinutes(30),
            'prepared_at' => now(),
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
            'prepared_at' => now()->subMinutes(30),
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
            'prepared_at' => now()->subHours(1),
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
            'cancellation_reason' => $this->faker->sentence(),
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
            'discount' => 0,
            'total' => max(0, $total),
        ]);
    }
}
