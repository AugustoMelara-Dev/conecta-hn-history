<?php

declare(strict_types=1);

namespace App\Application\Tenant\Validators;

use App\Application\Tenant\Commands\RegisterTenantCommand;

/**
 * RegisterTenantValidator - Structural Validation
 */
class RegisterTenantValidator
{
    public function rules(RegisterTenantCommand $command): array
    {
        return [
            'businessName' => ['required', 'string', 'min:3'],
            'slug' => ['required', 'alpha_dash', 'min:3', 'unique:tenants,slug'],
            'ownerName' => ['required', 'string'],
            'email' => ['required', 'email'],
            // HTTP layer already validates password_confirmation.
            // Command layer receives only canonical fields.
            'password' => ['required', 'min:8'],
        ];
    }
}
