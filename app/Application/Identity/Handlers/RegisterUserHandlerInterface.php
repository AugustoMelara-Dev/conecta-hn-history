<?php

declare(strict_types=1);

namespace App\Application\Identity\Handlers;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Results\RegisterUserResult;

/**
 * RegisterUserHandlerInterface - The Titanium Contract
 *
 * Defines the strict signature for user registration to allow
 * transparent decoration (Security, Telemetry, Caching).
 */
interface RegisterUserHandlerInterface
{
    public function handle(RegisterUserCommand $command): RegisterUserResult;
}
