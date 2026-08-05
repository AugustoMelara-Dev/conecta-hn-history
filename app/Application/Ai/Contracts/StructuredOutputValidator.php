<?php

declare(strict_types=1);

namespace App\Application\Ai\Contracts;

interface StructuredOutputValidator
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public function validate(array $data, array $rules): array;
}
