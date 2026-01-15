<?php

declare(strict_types=1);

namespace App\Domain\Common\Exceptions;

use Exception;

final class ConcurrentRequestException extends Exception
{
}
