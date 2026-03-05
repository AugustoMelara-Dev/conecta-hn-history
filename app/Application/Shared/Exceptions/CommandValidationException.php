<?php

declare(strict_types=1);

namespace App\Application\Shared\Exceptions;

use DomainException;

final class CommandValidationException extends DomainException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Command validation failed.');
    }
}
