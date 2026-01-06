<?php

declare(strict_types=1);

/**
 * TenantFactory - TenantGovernance Domain Factory
 *
 * CRITICAL: $model property explicitly defined for custom namespace resolution.
 */

namespace App\Domain\TenantGovernance\Database\Factories;

use App\Domain\TenantGovernance\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * CRITICAL: Explicit model binding for Domain namespace.
     *
     * @var class-string<Tenant>
     */
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->paragraph(),
            'address' => fake()->address(),
            'whatsapp_number' => fake()->phoneNumber(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'is_active' => true,
            'plan_type' => 'semilla',
            'subscription_status' => 'active',
        ];
    }

    /**
     * Indicate that the tenant is a Pro subscriber.
     */
    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_type' => 'pro',
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addYear(),
        ]);
    }

    /**
     * Indicate that the tenant is on trial.
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_type' => 'pro',
            'subscription_status' => 'trial',
            'subscription_ends_at' => now()->addDays(14),
        ]);
    }

    /**
     * Indicate that the tenant is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'subscription_status' => 'canceled',
        ]);
    }
}
