<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'provider' => $this->faker->randomElement(['bac', 'ficohsa', 'atlantida']), // Mapped to 'bank'
            'reference_id' => $this->faker->uuid(), // Mapped to 'reference_number'
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
