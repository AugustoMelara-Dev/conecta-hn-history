<?php

declare(strict_types=1);

namespace App\Application\Auth\Validators;

use App\Application\Auth\Commands\LoginViaSocialProviderCommand;
use App\Application\Auth\Handlers\LoginViaSocialProviderHandlerInterface;
use App\Application\Auth\Results\SocialLoginResult;
use App\Domain\Shared\Enums\ProviderName;
use App\Application\Shared\Contracts\ValidatorFactoryInterface;

/**
 * LoginViaSocialProviderCommandValidator - Clean Architecture Edition
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DI COMPLIANT: Injects ValidatorFactory contract, no static Facades.    ║
 * ║  DECORATOR PATTERN: Wraps inner handler with validation layer.          ║
 * ║  TESTABLE: Factory can be mocked in unit tests.                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class LoginViaSocialProviderCommandValidator implements LoginViaSocialProviderHandlerInterface
{
    public function __construct(
        private LoginViaSocialProviderHandlerInterface $inner,
        private ValidatorFactoryInterface $validatorFactory,
    ) {}

    public function handle(LoginViaSocialProviderCommand $command): SocialLoginResult
    {
        // Convert Enum cases to 'in:' rule — no framework coupling
        $allowedProviders = implode(',', array_column(ProviderName::cases(), 'value'));

        $this->validatorFactory->validate([
            'tenantId' => $command->tenantId,
            'providerName' => $command->providerName,
            'providerId' => $command->providerId,
            'email' => $command->email,
            'name' => $command->name,
            'idempotencyKey' => $command->idempotencyKey,
            'avatarUrl' => $command->avatarUrl,
        ], [
            'tenantId' => ['required', 'uuid'],
            'providerName' => ['required', 'in:' . $allowedProviders],
            'providerId' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'idempotencyKey' => ['required', 'uuid'],
            'avatarUrl' => ['nullable', 'url', 'max:1024'],
        ]);

        return $this->inner->handle($command);
    }
}
