<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\UpdateUserPasswordCommand;
use App\Application\Auth\Results\UpdateUserPasswordResult;

interface UpdateUserPasswordHandlerInterface
{
    public function handle(UpdateUserPasswordCommand $command): UpdateUserPasswordResult;
}
