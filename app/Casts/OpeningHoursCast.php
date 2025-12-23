<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class OpeningHoursCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (! $value) {
            return null;
        }

        $data = json_decode($value, true);

        return \Spatie\OpeningHours\OpeningHours::create($data ?? []);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        // Si ya es array, codificar a JSON. Si es objeto Spatie, json_encode maneja la serialización pública.
        return is_array($value) ? json_encode($value) : json_encode($value);
    }
}
