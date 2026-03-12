<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * TenantAware - Multi-Tenancy Identity Port
 *
 * Resolves the "Leaky Abstraction" where Actions accessed $user->tenant_id
 * directly on Authenticatable — a contract that does NOT guarantee that property.
 *
 * By implementing this interface, models declare their tenancy capabilities
 * explicitly, enabling type-safe tenant context resolution without implicit
 * schema coupling.
 *
 * @see App\Actions\Authentication\ResetPasswordAction
 * @see App\Actions\Authentication\UpdateUserPassword
 */
interface TenantAwareInterface
{
    /**
     * Get the identifier of the tenant the user belongs to.
     */
    public function getTenantId(): int|string|null;
}



