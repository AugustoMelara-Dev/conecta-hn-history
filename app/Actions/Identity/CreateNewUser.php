<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Application\UseCases\Identity\Commands\RegisterUserCommand;
use App\Application\UseCases\Identity\Handlers\RegisterUserHandler;
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
final class CreateNewUser implements CreatesNewUsers
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
        ])->validate();

        // 2. Map to Command (DTO)
        $command = new RegisterUserCommand(
            name: $input['name'],
            email: $input['email'],
            password: $input['password'],
            termsAccepted: true, // Validated above
            photoPath: null // Not in standard registration form usually
        );

        // 3. Delegate to Application Handler
        // Returns pure Domain Entity
        $userEntity = $this->handler->handle($command);

        // 4. The Bridge (Domain Entity -> Eloquent Model)
        // Fortify needs an Authenticatable Eloquent Model to login.
        // We re-fetch or wrap it. Ideally, Entity holds the ID.
        return User::find($userEntity->id);
    }
}
