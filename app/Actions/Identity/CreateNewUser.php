<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Handlers\RegisterUserHandler;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * CreateNewUser - Infrastructure Adapter for Fortify
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ADAPTER PATTERN: Bridges Fortify (Infra) to Application Layer.          ║
 * ║  RESPONSIBILITY: 1. Validate Input (Http/Form)                           ║
 * ║                  2. Map to Command (DTO)                                 ║
 * ║                  3. Delegate to Handler                                  ║
 * ║                  4. Return Eloquent Model (Bridge)                       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class CreateNewUser implements CreatesNewUsers
{
    // use \App\Actions\Fortify\PasswordValidationRules; // Trait not found in audit

    public function __construct(
        private readonly RegisterUserHandler $handler
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // 1. Validation (Infrastructure Concern)
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'], // Explicit rules
            'terms' => ['required', 'accepted'], // Assuming terms are mandatory
            'tenant_id' => ['nullable', 'string', 'exists:tenants,id'], // Validate if provided
        ])->validate();

        // 2. Map to Command (DTO)
        // STRICT TENANCY: Resolve tenant_id ONLY from validated input.
        // The caller (Controller/Fortify pipeline) is responsible for merging
        // session('tenant_id') into the input array before invoking this Action.
        $tenantId = $input['tenant_id'] ?? null;

        $command = new RegisterUserCommand(
            name: $input['name'],
            email: $input['email'],
            password: $input['password'],
            tenantName: $input['company_name'] ?? null, // Optional if strictly joining, mandatory if creating? Handler handles default.
            tenantId: $tenantId,
            role: $tenantId ? 'staff' : 'seller' // Default logic: Join=Staff, New=Owner (Legacy: Seller)
        );

        // 3. Delegate to Application Handler
        // Returns RegisterUserResult with the persisted user ID
        $result = $this->handler->handle($command);

        // 4. ARCHITECTURAL BRIDGE (Result -> Eloquent Model)
        // Fortify contract requires Authenticatable (Eloquent Model).
        // This fetch is NECESSARY, not redundant - the handler uses
        // Repository Pattern with Domain Entities, not Eloquent.
        // Using findOrFail() for failfast on race conditions.
        return User::findOrFail($result->id);
    }
}
