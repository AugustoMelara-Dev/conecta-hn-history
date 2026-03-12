<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Fortify\Actions\Identity;

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
 * CreateNewUser - Infrastructure Adapter for Fortify
 *
 * ARCHITECTURAL BRIDGE: Bridges Fortify (Infra) to Application Layer.
 * Relocated from app/Actions/Identity to Infrastructure layer.
 * RESPONSIBILITY: 1. Validate Input 2. Map to Command 3. Dispatch 4. Return Authenticatable
 */
final readonly class CreateNewUser implements CreatesNewUsers
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private ValidatorFactory $validatorFactory,
        private UserUniquenessCheckerInterface $uniquenessChecker,
        private UserReadProjection $userProjection,
        private TenantRateLimiterInterface $tenantRateLimiter,
    ) {}

    /**
     * @param  array<string, string>  $input
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
                decayMinutes: 1
            );
        }

        $command = new RegisterUserCommand(
            name: $input['name'],
            email: $input['email'],
            password: $input['password'],
            tenantName: $input['company_name'] ?? null,
            tenantId: $tenantId,
            role: $tenantId ? 'staff' : 'seller',
        );

        $result = $this->commandBus->dispatch($command);

        return $this->userProjection->getById((string) $result->id);
    }
}
