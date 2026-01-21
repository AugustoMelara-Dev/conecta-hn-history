<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use App\Domain\Shared\Exceptions\SchemaViolationException;

interface SchemaValidatorInterface
{
    /**
     * Validate a payload against a schema subject.
     *
     * @param  string  $subject  The logical name of the schema (e.g., 'user.registered.v1')
     * @param  array  $payload  The data to validate
     *
     * @throws SchemaViolationException If validation fails
     */
    public function validate(string $subject, array $payload): void;
}
