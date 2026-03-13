<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * TenantAwareAuthenticatable - Unified identity and tenancy contract.
 *
 * This compatibility surface lets ACL actions assert a single contract for
 * both authentication identity and tenant context.
 */
interface TenantAwareAuthenticatable extends Authenticatable, TenantAware
{
}