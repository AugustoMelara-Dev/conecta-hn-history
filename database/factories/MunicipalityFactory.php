<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class MunicipalityFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->city();
        return [
            'department_id' => Department::factory(),
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
        ];
    }
}
