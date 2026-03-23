<?php

declare(strict_types=1);

namespace App\Application\Auth\Validators;

use App\Application\Auth\Commands\UpdateUserPasswordCommand;
use App\Application\Auth\Handlers\UpdateUserPasswordHandlerInterface;
use App\Application\Auth\Results\UpdateUserPasswordResult;
use App\Application\Shared\Contracts\ValidatorFactoryInterface;
use App\Application\Shared\Exceptions\CommandValidationException;

/**
 * UpdateUserPasswordCommandValidator - Clean Architecture Edition
 *
 * Accepts a generic inner handler so it can wrap the shared middleware
 * decorators produced by PipelineBuilder while still exposing the typed
 * UpdateUserPasswordHandlerInterface at the boundary.
 */
final readonly class UpdateUserPasswordCommandValidator implements UpdateUserPasswordHandlerInterface
{
    public function __construct(
        private object $inner,
        private ValidatorFactoryInterface $validatorFactory,
    ) {}

    public function handle(UpdateUserPasswordCommand $command): UpdateUserPasswordResult
    {
        $this->validate($command);

        return $this->inner->handle($command);
    }

    /**
     * @throws CommandValidationException
     */
    private function validate(UpdateUserPasswordCommand $command): void
    {
        $this->validatorFactory->validate([
            'userId' => $command->userId,
            'requestingTenantId' => $command->requestingTenantId,
            'currentPassword' => $command->currentPassword,
            'newPassword' => $command->newPassword,
            'idempotencyKey' => $command->idempotencyKey,
        ], [
            'userId' => ['required', 'uuid'],
            'requestingTenantId' => ['required', 'uuid'],
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:8'],
            'idempotencyKey' => ['required', 'uuid'],
        ]);
    }
}
