<?php

declare(strict_types=1);

namespace App\Application\BusinessAi\Contracts;

use App\Models\Tenant;
use App\Models\User;

interface BusinessAiAuthorizerInterface
{
    public function authorize(User $user, Tenant $tenant): bool;
}
