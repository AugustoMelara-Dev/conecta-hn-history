<?php

declare(strict_types=1);

namespace App\Application\Auth\Decorators;

use App\Application\Auth\Commands\LoginViaSocialProviderCommand;
use App\Application\Auth\Handlers\LoginViaSocialProviderHandlerInterface;
use App\Application\Auth\Results\LoginResult;
use App\Application\Auth\Validators\LoginViaSocialProviderCommandValidator;
use InvalidArgumentException;

final class LoginViaSocialProviderValidationDecorator implements LoginViaSocialProviderHandlerInterface
{
    public function __construct(
        private LoginViaSocialProviderHandlerInterface $inner,
        private LoginViaSocialProviderCommandValidator $validator
    ) {}

    public function handle(LoginViaSocialProviderCommand $command): LoginResult
    {
        $this->validator->validate($command);
        return $this->inner->handle($command);
    }
}
