<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use DomainException;

final class UserAlreadyExistsException extends DomainException
{
    // Semantic Domain Exception
}
