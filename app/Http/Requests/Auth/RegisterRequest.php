<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Application\Identity\Commands\RegisterUserCommand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * RegisterRequest - User Registration Validation
 */
final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'tenant_name' => ['nullable', 'string', 'max:255'], // Optional
            // 'details' ignored as not in Command
        ];
    }

    public function toCommand(): RegisterUserCommand
    {
        $validated = $this->validated();

        // IDEMPOTENCY Extraction
        // Support Header (Standard) or Body (Fallback)
        $idempotencyKey = $this->header('X-Idempotency-Key') ?? $this->input('idempotency_key');

        return new RegisterUserCommand(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
            tenantName: $validated['tenant_name'] ?? null,
            slug: null, // Let Handler generate it
            source: 'api_registration', // Traceability
            idempotencyKey: $idempotencyKey,
            schemaVersion: 1
        );
    }
}
