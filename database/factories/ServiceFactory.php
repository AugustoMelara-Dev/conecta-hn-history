<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement([
                'Corte de Cabello',
                'Manicure',
                'Pedicure',
                'Tinte',
                'Masaje Relajante',
                'Facial',
                'Depilación',
            ]),
            'description' => fake()->sentence(),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'price' => fake()->randomFloat(2, 50, 500),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
