<?php

declare(strict_types=1);

namespace App\Actions\Authentication;

use App\Application\Auth\Commands\UpdateUserPasswordCommand;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\TenantAwareAuthenticatable;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use App\Application\Shared\Contracts\IdGeneratorInterface;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * UpdateUserPassword - Infrastructure Adapter for Authentication
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GATEWAY ONLY: Validates HTTP input and delegates to Application Layer. ║
 * ║  PURE: No Eloquent logic, no hashing here.                              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Moved from Fortify/ to Authentication/ for Screaming Architecture compliance.
 */
final readonly class UpdateUserPassword implements UpdatesUserPasswords
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private ValidatorFactory $validatorFactory,
        private IdGeneratorInterface $idGenerator,
        private \App\Application\Shared\Contracts\TenantRateLimiterInterface $tenantRateLimiter,
    ) {}

    /**
     * Validate and update the user's password.
     *
     * @param  mixed  $user
     * @param  array<string, string>  $input
     */
    public function update($user, array $input): void
    {
        // PRODUCTION SAFETY: Explicit guard replaces assert() which is compiled
        // out in production (zend.assertions = -1). SEC-01 hardening.
        if (! $user instanceof TenantAwareAuthenticatable) {
            throw new \InvalidArgumentException('User must implement TenantAwareAuthenticatable.');
        }

        if (! $user->getTenantId()) {
            throw new \RuntimeException('Cannot update password: user has no tenant context.');
        }

        // RATE LIMITING: 5 updates per minute per tenant
        $this->tenantRateLimiter->ensureNotExceeded(
            actionName: self::class,
            tenantId: (string) $user->getTenantId(),
            maxAttempts: 5,
            decayMinutes: 1
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
