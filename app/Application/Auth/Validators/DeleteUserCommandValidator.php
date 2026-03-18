<?php

declare(strict_types=1);

namespace App\Application\Auth\Validators;

use App\Application\Auth\Commands\DeleteUserCommand;
use App\Application\Auth\Handlers\DeleteUserHandlerInterface;
use App\Application\Auth\Results\DeleteUserResult;
use App\Application\Shared\Contracts\ValidatorFactoryInterface;

/**
 * DeleteUserCommandValidator - Clean Architecture Edition
 *
 * Accepts a generic inner handler so it can wrap the shared middleware
 * decorators produced by PipelineBuilder while still exposing the typed
 * DeleteUserHandlerInterface at the boundary.
 */
final readonly class DeleteUserCommandValidator implements DeleteUserHandlerInterface
{
    public function __construct(
        private object $inner,
        private ValidatorFactoryInterface $validatorFactory,
    ) {}

    public function handle(DeleteUserCommand $command): DeleteUserResult
    {
        $this->validate($command);

        return $this->inner->handle($command);
    }

    /**
     * @throws ValidationException
     */
    private function validate(DeleteUserCommand $command): void
    {
        $this->validatorFactory->validate([
            'userId' => $command->userId,
            'requestingTenantId' => $command->requestingTenantId,
            'idempotencyKey' => $command->idempotencyKey,
        ], [
            'userId' => ['required', 'uuid'],
            'requestingTenantId' => ['required', 'uuid'],
            'idempotencyKey' => ['required', 'uuid'],
        ]);
    }
}
