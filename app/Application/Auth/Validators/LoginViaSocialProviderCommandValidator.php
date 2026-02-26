<?php

declare(strict_types=1);

namespace App\Application\Auth\Validators;

use App\Application\Auth\Commands\LoginViaSocialProviderCommand;
use App\Application\Auth\Handlers\LoginViaSocialProviderHandlerInterface;
use App\Application\Auth\Results\SocialLoginResult;
use App\Domain\Shared\Enums\ProviderName;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

/**
 * LoginViaSocialProviderCommandValidator - Clean Architecture Edition
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DI COMPLIANT: Injects ValidatorFactory contract, no static Facades.    ║
 * ║  DECORATOR PATTERN: Wraps inner handler with validation layer.          ║
 * ║  TESTABLE: Factory can be mocked in unit tests.                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class LoginViaSocialProviderCommandValidator implements LoginViaSocialProviderHandlerInterface
{
    public function __construct(
        private LoginViaSocialProviderHandlerInterface $inner,
        private ValidatorFactory $validatorFactory,
    ) {}

    public function handle(LoginViaSocialProviderCommand $command): SocialLoginResult
    {
        $validator = $this->validatorFactory->make([
            'tenantId' => $command->tenantId,
            'providerName' => $command->providerName,
            'providerId' => $command->providerId,
            'email' => $command->email,
            'name' => $command->name,
            'idempotencyKey' => $command->idempotencyKey,
            'avatarUrl' => $command->avatarUrl,
        ], [
            'tenantId' => ['required', 'uuid'],
            'providerName' => ['required', new Enum(ProviderName::class)],
            'providerId' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'idempotencyKey' => ['required', 'uuid'],
            'avatarUrl' => ['nullable', 'url', 'max:1024'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $this->inner->handle($command);
    }
}
