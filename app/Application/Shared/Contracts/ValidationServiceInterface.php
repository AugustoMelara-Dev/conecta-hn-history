<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use App\Domain\Shared\Exceptions\ValidationException;

/**
 * ValidationServiceInterface
 *
 * Abstraction layer for data validation.
 * Decouples the Application from concrete framework implementations (Laravel Validator, Symfony, etc).
 */
interface ValidationServiceInterface
{
    /**
     * Validate data against rules.
     *
     * @param  array  $data  Input data
     * @param  array  $rules  Validation rules
     *
     * @throws ValidationException If validation fails
     */
    public function validate(array $data, array $rules): void;
}
