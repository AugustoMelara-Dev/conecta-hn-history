<?php

declare(strict_types=1);

namespace App\Application\Auth\Decorators;

use App\Application\Auth\Commands\DeleteUserCommand;
use App\Application\Auth\Handlers\DeleteUserHandlerInterface;
use App\Application\Auth\Results\DeleteUserResult;
use App\Application\Auth\Validators\DeleteUserCommandValidator;

final class DeleteUserValidationDecorator implements DeleteUserHandlerInterface
{
    public function __construct(
        private DeleteUserHandlerInterface $inner,
        private DeleteUserCommandValidator $validator
    ) {}

    public function handle(DeleteUserCommand $command): DeleteUserResult
    {
        $this->validator->validate($command);
        return $this->inner->handle($command);
    }
}
