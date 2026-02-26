<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RegisterUserRequest - The Shield
 *
 * Validates data integrity before it reaches the Domain.
 * Leverages SQL Projections for high-performance uniqueness checks.
 */
class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'tenant_name' => ['required', 'string', 'max:255'],
            'tenant_domain' => ['required', 'string', 'alpha_dash', 'max:255', 'unique:tenants,slug'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'tenant_name' => 'organization name',
            'tenant_domain' => 'organization domain',
        ];
    }
}
