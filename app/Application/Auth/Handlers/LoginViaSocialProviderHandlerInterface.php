<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\LoginViaSocialProviderCommand;
use App\Application\Auth\Results\SocialLoginResult;

interface LoginViaSocialProviderHandlerInterface
{
    public function handle(LoginViaSocialProviderCommand $command): SocialLoginResult;
}
