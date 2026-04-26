<?php

declare(strict_types=1);

namespace App\Application\Tenant\Validators;

use App\Application\Tenant\Commands\RegisterTenantCommand;

/**
 * RegisterTenantValidator - Structural Validation
 */
final readonly class RegisterTenantValidator
{
    public function rules(RegisterTenantCommand $command): array
    {
        return [
            'businessName' => ['required', 'string', 'min:3', 'max:255'],
            'slug' => ['required', 'string', 'alpha_dash', 'min:3', 'max:100', 'unique:tenants,slug'],
            'ownerName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'cityId' => ['required', 'integer', 'exists:cities,id'],
            'categoryId' => ['required', 'integer', 'exists:categories,id'],
            'businessType' => ['required', 'in:retail,food,service,hybrid'],
            'rubroKey' => ['nullable', 'string', 'max:120'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'selectedPlan' => ['nullable', 'in:free,pro'],
            'logoPath' => ['nullable', 'string', 'max:2048'],
            'idempotencyKey' => ['nullable', 'uuid'],
        ];
    }
}
