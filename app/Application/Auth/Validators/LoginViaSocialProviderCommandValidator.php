<?php

declare(strict_types=1);

namespace App\Application\Auth\Validators;

use App\Application\Auth\Commands\LoginViaSocialProviderCommand;
use App\Application\Auth\Handlers\LoginViaSocialProviderHandlerInterface;
use App\Application\Auth\Results\SocialLoginResult;
use App\Application\Shared\Contracts\ValidatorFactoryInterface;
use App\Domain\Shared\Enums\ProviderName;

/**
 * LoginViaSocialProviderCommandValidator - Clean Architecture Edition
 *
 * Accepts a generic inner handler so it can wrap the shared middleware
 * decorators produced by PipelineBuilder while still exposing the typed
 * LoginViaSocialProviderHandlerInterface at the boundary.
 */
final readonly class LoginViaSocialProviderCommandValidator implements LoginViaSocialProviderHandlerInterface
{
    public function __construct(
        private object $inner,
        private ValidatorFactoryInterface $validatorFactory,
    ) {}

    public function handle(LoginViaSocialProviderCommand $command): SocialLoginResult
    {
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