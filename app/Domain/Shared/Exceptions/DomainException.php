<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use App\Exceptions\DomainException as BaseDomainException;

/**
 * Domain layer exception alias.
 *
 * This class extends the base DomainException from App\Exceptions
 * to provide a Domain\Shared namespace-compatible reference.
 */
abstract class DomainException extends BaseDomainException
{
    // Inherits all functionality from App\Exceptions\DomainException
}
