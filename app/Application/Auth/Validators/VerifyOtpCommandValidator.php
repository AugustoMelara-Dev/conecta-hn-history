<?php

declare(strict_types=1);

namespace App\Application\Auth\Validators;

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Application\Auth\Results\VerifyOtpResult;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;

/**
 * VerifyOtpCommandValidator - Clean Architecture Edition
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DI COMPLIANT: Injects ValidatorFactory contract, no static Facades.    ║
 * ║  DECORATOR PATTERN: Wraps inner handler with validation layer.          ║
 * ║  TESTABLE: Factory can be mocked in unit tests.                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class VerifyOtpCommandValidator implements VerifyOtpHandlerInterface
{
    public function __construct(
        private VerifyOtpHandlerInterface $inner,
        private ValidatorFactory $validatorFactory,
    ) {}

    public function handle(VerifyOtpCommand $command): VerifyOtpResult
    {
        $this->validate($command);

        return $this->inner->handle($command);
    }

    /**
     * @throws ValidationException
     */
    private function validate(VerifyOtpCommand $command): void
    {
        $validator = $this->validatorFactory->make([
            'identifier' => $command->identifier,
            'code' => $command->code,
            'tenantId' => $command->tenantId,
            'idempotencyKey' => $command->idempotencyKey,
        ], [
            'identifier' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'digits:6'],
            'tenantId' => ['required', 'uuid'],
            'idempotencyKey' => ['required', 'uuid'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
