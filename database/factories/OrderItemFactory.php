<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * OrderItemFactory - Factory for OrderItem Model
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRITICAL: Required for InventoryManager concurrency tests              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @var class-string<OrderItem>
     */
    protected $model = OrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = $this->faker->randomFloat(2, 50, 500);
        $quantity = $this->faker->numberBetween(1, 5);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => $this->faker->words(3, true),
            'product_price' => $unitPrice,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'total_price' => $unitPrice * $quantity,
            'line_total' => $unitPrice * $quantity,
            'selected_options' => null,
            'notes' => null,
        ];
    }

    /**
     * Set a specific quantity.
     */
    public function withQuantity(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $quantity,
            'total_price' => ($attributes['unit_price'] ?? 100) * $quantity,
            'line_total' => ($attributes['unit_price'] ?? 100) * $quantity,
        ]);
    }

    /**
     * Associate with a specific order.
     */
    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
        ]);
    }

    /**
     * Associate with a specific product.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'unit_price' => $product->price,
            'total_price' => $product->price * ($attributes['quantity'] ?? 1),
            'line_total' => $product->price * ($attributes['quantity'] ?? 1),
        ]);
    }
}
