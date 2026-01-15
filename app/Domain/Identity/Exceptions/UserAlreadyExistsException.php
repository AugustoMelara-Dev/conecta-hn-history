<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use DomainException;

class UserAlreadyExistsException extends DomainException
{
    // Semantic Domain Exception
}
