<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CityFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->city();
        
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'municipality_id' => null, // Optional
            'coordinates' => [
                'lat' => fake()->latitude(13, 16), // Honduras latitude range
                'lng' => fake()->longitude(-89, -83), // Honduras longitude range
            ],
        ];
    }
}
