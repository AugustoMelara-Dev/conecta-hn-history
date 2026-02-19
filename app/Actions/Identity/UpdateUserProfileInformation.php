<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Application\Identity\Contracts\UserUniquenessCheckerInterface;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\TenantAwareAuthenticatable;
use App\Application\Identity\Commands\UpdateUserProfileCommand;
use App\Rules\UniqueUser;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * UpdateUserProfileInformation - Infrastructure Adapter for Identity
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GATEWAY ONLY: Validates HTTP input and delegates to Application Layer. ║
 * ║  PURE: No Eloquent logic, no side-effects here.                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * REFACTORED: Fixed phantom namespace import (Auth\Commands → User\Commands).
 * REFACTORED: Validator facade → injected Factory contract.
 * REFACTORED: Added mandatory requestingTenantId to Command.
 * RELOCATED: Moved from Authentication/ to Identity/ for Screaming Architecture compliance.
 *            Updating a user profile is an Identity lifecycle event, not Authentication.
 */
final readonly class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private ValidatorFactory $validatorFactory,
        private UserUniquenessCheckerInterface $uniquenessChecker,
        private \App\Application\Shared\Contracts\TenantRateLimiterInterface $tenantRateLimiter,
    ) {}

    /**
     * Validate and update the given user's profile information.
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
            throw new \RuntimeException('Cannot update profile: user has no tenant context.');
        }

        // RATE LIMITING: 20 updates per minute per tenant
        $this->tenantRateLimiter->ensureNotExceeded(
            actionName: self::class,
            tenantId: (string) $user->getTenantId(),
            maxAttempts: 20,
            decayMinutes: 1
        );

        $this->validatorFactory->make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                new UniqueUser($this->uniquenessChecker, tenantId: (string) $user->getTenantId(), exceptUserId: (string) $user->getAuthIdentifier()),
            ],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
        ])->validateWithBag('updateProfileInformation');

        $this->commandBus->dispatch(
            new UpdateUserProfileCommand(
                userId: (string) $user->getAuthIdentifier(),
                requestingTenantId: (string) $user->getTenantId(),
                name: $input['name'],
                email: $input['email'],
            )
        );
    }
}
