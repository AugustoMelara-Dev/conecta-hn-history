<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

interface ValidatorFactoryInterface
{
    /**
     * Validates $data against $rules.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @throws \App\Application\Shared\Exceptions\CommandValidationException  on failure
     */
    public function validate(array $data, array $rules): void;
}
