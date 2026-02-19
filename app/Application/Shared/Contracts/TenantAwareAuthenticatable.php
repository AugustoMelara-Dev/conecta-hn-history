<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * TenantAwareAuthenticatable - Unified Identity + Tenancy Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  INTERFACE SEGREGATION: Combines Authenticatable (Identity) and         ║
 * ║  TenantAware (Multi-tenancy) into a single assertion target.            ║
 * ║  Eliminates dual-assertion boilerplate in Actions.                      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @see App\Application\Shared\Contracts\TenantAware
 * @see App\Actions\Authentication\ResetPasswordAction
 */
interface TenantAwareAuthenticatable extends Authenticatable, TenantAware
{
    // Inherits getTenantId(): int|string from TenantAware
    // Inherits getAuthIdentifier(), getAuthPassword(), etc. from Authenticatable
}
