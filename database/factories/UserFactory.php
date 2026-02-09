<?php

declare(strict_types=1);

/**
 * UserFactory - Identity Domain Factory
 *
 * MIGRATION NOTE: Moved from database/factories/UserFactory.php
 * to App\Domain\Identity\Database\Factories\UserFactory.php
 *
 * CRITICAL: $model property is explicitly defined because Laravel
 * cannot auto-discover factory-model binding in custom namespaces.
 */

namespace Database\Factories;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * CRITICAL: This explicit binding is REQUIRED for custom namespace structures.
     * Without it, Laravel attempts to resolve App\Models\User which no longer exists.
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * ANTI-RECURSION: tenant_id is NULL by default.
     * Use withTenant() state method to associate a tenant.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::CUSTOMER,
            // CRITICAL: tenant_id is NULL to avoid Tenant::factory() recursion
            // which triggers LogsActivity and causes MissingAttributeException.
            // Tests should use forTenant() state method or create Tenant separately.
            'tenant_id' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a super admin user.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::SUPER_ADMIN,
        ]);
    }

    /**
     * Create a tenant owner user.
     */
    public function tenantOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::CUSTOMER,
        ]);
    }

    /**
     * Associate user with a new tenant (breaks recursion by creating tenant AFTER user).
     *
     * Usage: User::factory()->withTenant()->create()
     */
    public function withTenant(): static
    {
        return $this->afterCreating(function (User $user) {
            $tenant = Tenant::factory()->create(['user_id' => $user->id]);
            $user->update(['tenant_id' => $tenant->id]);
        });
    }
}
