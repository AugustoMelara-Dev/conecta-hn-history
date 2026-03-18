<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockStatus;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * ProductFactory - Inventory-Compliant Factory for Product Model
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRITICAL: All inventory fields must align with InventoryManager        ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  REQUIREMENTS:                                                           ║
 * ║    1. stock_status must use StockStatus ENUM (not raw strings)          ║
 * ║    2. manage_stock must be true for inventory tests                     ║
 * ║    3. stock_quantity must be integer, defaults to 0                     ║
 * ║    4. tenant_id association for multi-tenancy compliance                ║
 * ║                                                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Explicit model binding for domain namespace resolution.
     *
     * @var class-string<Product>
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            // ═══════════════════════════════════════════════════════════════════
            // FOREIGN KEYS & IDENTITY
            // ═══════════════════════════════════════════════════════════════════
            'tenant_id' => Tenant::factory(),

            // ═══════════════════════════════════════════════════════════════════
            // CORE PRODUCT DATA
            // ═══════════════════════════════════════════════════════════════════
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 50, 5000),

            // ═══════════════════════════════════════════════════════════════════
            // INVENTORY FIELDS (CRITICAL FOR CONCURRENCY TESTS)
            // ═══════════════════════════════════════════════════════════════════
            'manage_stock' => true,
            'stock_quantity' => 10,
            'low_stock_threshold' => 5,
            'allow_backorders' => false,
            'stock_status' => StockStatus::InStock, // ENUM, not string

            // ═══════════════════════════════════════════════════════════════════
            // DISPLAY & MEDIA
            // ═══════════════════════════════════════════════════════════════════
            'image_path' => $this->faker->randomElement([
                'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=500&q=60',
                'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=500&q=60',
                'https://images.unsplash.com/photo-1572635196237-14b3f281503f?auto=format&fit=crop&w=500&q=60',
            ]),
            'is_visible' => true,
            'is_featured' => false,
            'is_new' => false,
            'is_impulse_buy' => false,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STATE METHODS FOR TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Set product as out of stock (for concurrency tests).
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
            'stock_status' => StockStatus::OutOfStock,
        ]);
    }

    /**
     * Set product with specific stock level.
     */
    public function withStock(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => $quantity,
            'stock_status' => $quantity > 0 ? StockStatus::InStock : StockStatus::OutOfStock,
        ]);
    }

    /**
     * Set product as unmanaged stock (skipped by InventoryManager).
     */
    public function unmanagedStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'manage_stock' => false,
            'stock_status' => StockStatus::InStock,
        ]);
    }

    /**
     * Associate with specific tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Set low stock status.
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 2,
            'stock_status' => StockStatus::LowStock,
        ]);
    }
}
