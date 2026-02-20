<?php

declare(strict_types=1);

namespace App\Application\Auth\Validators;

use App\Application\Auth\Commands\UpdateUserPasswordCommand;
use App\Application\Auth\Handlers\UpdateUserPasswordHandlerInterface;
use App\Application\Auth\Results\UpdateUserPasswordResult;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * UpdateUserPasswordCommandValidator - Clean Architecture Edition
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DI COMPLIANT: Injects ValidatorFactory contract, no static Facades.    ║
 * ║  DECORATOR PATTERN: Wraps inner handler with validation layer.          ║
 * ║  TESTABLE: Factory can be mocked in unit tests.                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class UpdateUserPasswordCommandValidator implements UpdateUserPasswordHandlerInterface
{
    public function __construct(
        private object $inner,
        private ValidatorFactory $validatorFactory,
    ) {}

    public function handle(UpdateUserPasswordCommand $command): UpdateUserPasswordResult
    {
        $this->validate($command);

        return $this->inner->handle($command);
    }

    /**
     * @throws ValidationException
     */
    private function validate(UpdateUserPasswordCommand $command): void
    {
        $validator = $this->validatorFactory->make([
            'userId' => $command->userId,
            'requestingTenantId' => $command->requestingTenantId,
            'currentPassword' => $command->currentPassword,
            'newPassword' => $command->newPassword,
            'idempotencyKey' => $command->idempotencyKey,
        ], [
            'userId' => ['required', 'uuid'],
            'requestingTenantId' => ['required', 'uuid'],
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'idempotencyKey' => ['required', 'uuid'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
