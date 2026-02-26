<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Contracts\UserReadProjection;
use App\Application\Identity\Contracts\UserUniquenessCheckerInterface;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Rules\UniqueUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * CreateNewUser - Infrastructure Adapter for Fortify
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ADAPTER PATTERN: Bridges Fortify (Infra) to Application Layer.          ║
 * ║  RESPONSIBILITY: 1. Validate Input (Http/Form)                           ║
 * ║                  2. Map to Command (DTO)                                 ║
 * ║                  3. Dispatch via CommandBus                               ║
 * ║                  4. Return Authenticatable via Projection Port            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * REFACTORED: Strict DI — replaced concrete RegisterUserHandler with
 * CommandBusInterface for uniform delegation across all Actions.
 * DECOUPLED: User::findOrFail() replaced with UserReadProjection port.
 */
final readonly class CreateNewUser implements CreatesNewUsers
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private ValidatorFactory $validatorFactory,
        private UserUniquenessCheckerInterface $uniquenessChecker,
        private UserReadProjection $userProjection,
        private \App\Application\Shared\Contracts\TenantRateLimiterInterface $tenantRateLimiter,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): Authenticatable
    {
        // 1. Validation (Infrastructure Concern)
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

        // 2. Map to Command (DTO)
        // STRICT TENANCY: Resolve tenant_id ONLY from validated input.
        // The caller (Controller/Fortify pipeline) is responsible for merging
        // session('tenant_id') into the input array before invoking this Action.
        // DOMAIN INVARIANT EXCEPTION: Nullable tenant_id supports the "Create Organization"
        // flow where a new user registers and creates their own tenant simultaneously.
        // Unlike all other Actions, this entry point does NOT throw RuntimeException
        // for missing tenant context — the Handler resolves tenancy downstream.
        $tenantId = $input['tenant_id'] ?? null;

        // RATE LIMITING: If tenant context is known (Invitation Flow), apply strict limits (10/min)
        // If unknown (Owner Registration), apply implicit IP-based throttles via Fortify Route (outside this scope)
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

        // 3. Dispatch via CommandBus (Application Layer)
        // SYNC REQUIRED: Fortify's CreatesNewUsers contract demands immediate return
        // of an Authenticatable instance. This command MUST execute synchronously.
        // If queued, the subsequent getById() read will race against the write,
        // resulting in a 404 and broken registration flow.
        $result = $this->commandBus->dispatch($command);

        // ARCHITECTURAL BRIDGE: Fortify's CreatesNewUsers contract requires returning
        // an Authenticatable instance. We mediate through the UserReadProjection port
        // instead of calling User::findOrFail() directly — keeping this Action ORM-free.
        return $this->userProjection->getById((string) $result->id);
    }
}
