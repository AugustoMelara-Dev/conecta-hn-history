<?php

declare(strict_types=1);

namespace App\Actions\Authentication;

use App\Application\Auth\Commands\UpdateUserPasswordCommand;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\IdGeneratorInterface;
use App\Application\Shared\Contracts\TenantAwareAuthenticatable;
use App\Application\Shared\Contracts\TenantRateLimiterInterface;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use RuntimeException;

/**
 * UpdateUserPassword - Fortify ACL Adapter
 *
 * Translates the framework password-update signal into an UpdateUserPasswordCommand.
 * Uses getAuthIdentifier() and getTenantId() from TenantAwareAuthenticatable and
 * validates tenant context before dispatch.
 */
final readonly class UpdateUserPassword implements UpdatesUserPasswords
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private ValidatorFactory $validatorFactory,
        private IdGeneratorInterface $idGenerator,
        private TenantRateLimiterInterface $tenantRateLimiter,
    ) {}

    /**
     * @param  mixed                 $user
     * @param  array<string, string> $input
     */
    public function update($user, array $input): void
    {
        if (! $user instanceof TenantAwareAuthenticatable) {
            throw new InvalidArgumentException(
                'User must implement TenantAwareAuthenticatable to update password in tenant context.'
            );
        }

        if (! $user->getTenantId()) {
            throw new RuntimeException('Cannot update password: user has no tenant context.');
        }

        $this->tenantRateLimiter->ensureNotExceeded(
            actionName: self::class,
            tenantId: (string) $user->getTenantId(),
            maxAttempts: 5,
            decayMinutes: 1,
        );

        $this->validatorFactory->make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validate();

        $this->commandBus->dispatch(
            new UpdateUserPasswordCommand(
                userId: (string) $user->getAuthIdentifier(),
                requestingTenantId: (string) $user->getTenantId(),
                currentPassword: $input['current_password'],
                newPassword: $input['password'],
                refreshRememberToken: true,
                idempotencyKey: $this->idGenerator->generate(),
            )
        );
    }
}
