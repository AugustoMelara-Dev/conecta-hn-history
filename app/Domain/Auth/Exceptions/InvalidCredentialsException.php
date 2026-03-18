<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use DomainException;

final class InvalidCredentialsException extends DomainException {}
