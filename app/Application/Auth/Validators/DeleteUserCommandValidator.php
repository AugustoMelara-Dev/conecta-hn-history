<?php

declare(strict_types=1);

namespace App\Application\Auth\Validators;

use App\Application\Auth\Commands\DeleteUserCommand;
use App\Application\Auth\Handlers\DeleteUserHandlerInterface;
use App\Application\Auth\Results\DeleteUserResult;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;

/**
 * DeleteUserCommandValidator - Clean Architecture Edition
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DI COMPLIANT: Injects ValidatorFactory contract, no static Facades.    ║
 * ║  DECORATOR PATTERN: Wraps inner handler with validation layer.          ║
 * ║  TESTABLE: Factory can be mocked in unit tests.                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class DeleteUserCommandValidator implements DeleteUserHandlerInterface
{
    public function __construct(
        private DeleteUserHandlerInterface $inner,
        private ValidatorFactory $validatorFactory,
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
        $validator = $this->validatorFactory->make([
            'userId' => $command->userId,
            'requestingTenantId' => $command->requestingTenantId,
            'idempotencyKey' => $command->idempotencyKey,
        ], [
            'userId' => ['required', 'uuid'],
            'requestingTenantId' => ['required', 'uuid'],
            'idempotencyKey' => ['required', 'uuid'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
