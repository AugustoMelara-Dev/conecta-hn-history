<?php

declare(strict_types=1);

namespace App\Application\Shared\Common\Exceptions;

use DomainException;

final class RateLimitExceededException extends DomainException
{
}
