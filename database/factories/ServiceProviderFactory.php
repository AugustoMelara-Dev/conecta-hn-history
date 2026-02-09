<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServiceProvider;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceProviderFactory extends Factory
{
    protected $model = ServiceProvider::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'schedule_config' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withSchedule(array $schedule): static
    {
        return $this->state(fn () => ['schedule_config' => $schedule]);
    }
}
