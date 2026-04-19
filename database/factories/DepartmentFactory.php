<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->state();
        $slugBase = Str::slug($name);

        return [
            'name' => $name,
            'slug' => $slugBase.'-'.Str::lower(Str::random(6)),
        ];
    }
}
