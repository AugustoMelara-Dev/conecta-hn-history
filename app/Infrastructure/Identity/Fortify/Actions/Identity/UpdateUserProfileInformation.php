<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Fortify\Actions\Identity;

use App\Application\Identity\Commands\UpdateUserProfileCommand;
use App\Application\Identity\Contracts\UserUniquenessCheckerInterface;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\TenantAwareAuthenticatable;
use App\Application\Shared\Contracts\TenantRateLimiterInterface;
use App\Rules\UniqueUser;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * UpdateUserProfileInformation - Infrastructure Adapter for Fortify
 *
 * Relocated from app/Actions/Identity to Infrastructure layer.
 * Gateway only: Validates HTTP input and delegates to Application Layer.
 */
final readonly class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private ValidatorFactory $validatorFactory,
        private UserUniquenessCheckerInterface $uniquenessChecker,
        private TenantRateLimiterInterface $tenantRateLimiter,
    ) {}

    /**
     * @param  mixed  $user
     * @param  array<string, string>  $input
     */
    public function update($user, array $input): void
    {
        if (! $user instanceof TenantAwareAuthenticatable) {
            throw new \InvalidArgumentException('User must implement TenantAwareAuthenticatable.');
        }

        if (! $user->getTenantId()) {
            throw new \RuntimeException('Cannot update profile: user has no tenant context.');
        }

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
