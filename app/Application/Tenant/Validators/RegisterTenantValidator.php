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
            'businessName' => ['required', 'string', 'min:3'],
            'slug' => ['required', 'alpha_dash', 'min:3', 'unique:tenants,id'], // domain_prefix in spec, usually maps to slug
            'ownerName' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
        ];
    }
}
