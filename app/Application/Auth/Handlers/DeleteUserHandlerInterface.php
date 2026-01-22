<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\DeleteUserCommand;
use App\Application\Auth\Results\DeleteUserResult;

interface DeleteUserHandlerInterface
{
    public function handle(DeleteUserCommand $command): DeleteUserResult;
}
