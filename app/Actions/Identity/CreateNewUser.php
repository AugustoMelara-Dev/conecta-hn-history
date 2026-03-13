<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Contracts\UserReadProjection;
use App\Application\Identity\Contracts\UserUniquenessCheckerInterface;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\TenantRateLimiterInterface;
use App\Rules\UniqueUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * CreateNewUser - Fortify ACL Adapter
 *
 * SYNC REQUIRED: Fortify expects an immediate Authenticatable return after dispatch.
 * DOMAIN INVARIANT EXCEPTION: tenant_id may be null because this is the genesis
 * registration flow for creating a new organization boundary.
 */
final readonly class CreateNewUser implements CreatesNewUsers
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private ValidatorFactory $validatorFactory,
        private UserUniquenessCheckerInterface $uniquenessChecker,
        private UserReadProjection $userReadProjection,
        private TenantRateLimiterInterface $tenantRateLimiter,
    ) {}

    /**
     * @param  array<string, string> $input
     */
    public function create(array $input): Authenticatable
    {
        $this->validatorFactory->make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                new UniqueUser($this->uniquenessChecker),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms' => ['required', 'accepted'],
            'tenant_id' => ['nullable', 'string', 'exists:tenants,id'],
        ])->validate();

        $tenantId = $input['tenant_id'] ?? null;

        if ($tenantId) {
            $this->tenantRateLimiter->ensureNotExceeded(
                actionName: self::class,
                tenantId: $tenantId,
                maxAttempts: 10,
                decayMinutes: 1,
            );
        }

        $result = $this->commandBus->dispatch(
            new RegisterUserCommand(
                name: $input['name'],
                email: $input['email'],
                password: $input['password'],
                tenantName: $input['company_name'] ?? null,
                tenantId: $tenantId,
                role: $tenantId ? 'staff' : 'seller',
            )
        );

        return $this->userReadProjection->getById((string) ($result?->id ?? ''));
    }
}
