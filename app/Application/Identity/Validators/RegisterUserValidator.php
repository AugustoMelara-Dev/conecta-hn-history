<?php

declare(strict_types=1);

namespace App\Application\Identity\Validators;

use App\Application\Identity\Commands\RegisterUserCommand;
use Illuminate\Validation\Rules\Password;

/**
 * RegisterUserValidator - Decoupled Logic
 *
 * Keeps the Command as a Pure Data Structure by externalizing validation rules.
 */
class RegisterUserValidator
{
    /**
     * Get the validation rules that apply to the command.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(RegisterUserCommand $command): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email', // Enforces email uniqueness globally
            ],
            'password' => ['required', 'string', Password::defaults()],
            'tenantName' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:tenants,slug'],
        ];
    }
}
