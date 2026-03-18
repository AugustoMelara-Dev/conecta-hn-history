<?php

declare(strict_types=1);

namespace App\Application\Auth\Validators;

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Application\Auth\Results\VerifyOtpResult;
use App\Application\Shared\Contracts\ValidatorFactoryInterface;

/**
 * VerifyOtpCommandValidator - Clean Architecture Edition
 *
 * Accepts a generic inner handler so it can wrap the shared middleware
 * decorators produced by PipelineBuilder while still exposing the typed
 * VerifyOtpHandlerInterface at the boundary.
 */
final readonly class VerifyOtpCommandValidator implements VerifyOtpHandlerInterface
{
    public function __construct(
        private object $inner,
        private ValidatorFactoryInterface $validatorFactory,
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
        $this->validatorFactory->validate([
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
    }
}
