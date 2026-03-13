<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * TenantAware - Backward-compatible tenancy contract alias.
 *
 * Keeps legacy actions and architecture tests stable while the canonical
 * tenancy capability contract remains expressed by TenantAwareInterface.
 */
interface TenantAware extends TenantAwareInterface
{
}