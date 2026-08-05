<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

/**
 * TenantAwarePolicy - Base Policy for Multi-Tenant Authorization
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * SECURITY CRITICAL - AUTHORIZATION FOUNDATION
 * ══════════════════════════════════════════════════════════════════════════════
 *
 * This abstract base policy enforces tenant ownership verification as a
 * prerequisite for ALL authorization decisions. Child policies MUST call
 * parent methods or explicitly verify ownership.
 *
 * USAGE:
 * ```php
 * class ProductPolicy extends TenantAwarePolicy
 * {
 *     public function update(User $user, Product $product): bool
 *     {
 *         // This automatically includes tenant ownership check
 *         return $this->ownsResource($user, $product);
 *     }
 * }
 * ```
 *
 * SECURITY GUARANTEES:
 * - Tenant ownership is verified BEFORE any other authorization logic
 * - Returns false (deny) if user has no tenants
 * - Returns false if resource doesn't belong to user's tenant
 * - Provides helper methods for common authorization patterns
 */
abstract class TenantAwarePolicy
{
    use HandlesAuthorization;

    /**
     * Check if the user owns the resource (belongs to same tenant).
     *
     * This is the CORE security check that must be called on all
     * modify/delete operations.
     */
    protected function ownsResource(User $user, Model $resource): bool
    {
        // Resource must have tenant_id
        if (! isset($resource->tenant_id)) {
            return false;
        }

        // Get user's accessible tenant IDs
        $userTenantIds = $this->getUserTenantIds($user);

        // No tenants = deny
        if (empty($userTenantIds)) {
            return false;
        }

        // Verify ownership
        return in_array((string) $resource->tenant_id, $userTenantIds, true);
    }

    /**
     * Get the tenant IDs accessible by the user.
     */
    protected function getUserTenantIds(User $user): array
    {
        // PERFORMANCE FIX (VC-AUDIT-005): Memoize to prevent N+1 queries during multiple policy checks
        if (isset($user->_memoized_tenant_ids)) {
            return array_map('strval', $user->_memoized_tenant_ids);
        }

        return $user->_memoized_tenant_ids = $user->tenants()
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * Get the primary (first) tenant ID for the user.
     */
    protected function getUserPrimaryTenantId(User $user): ?string
    {
        $tenantId = $user->tenants()->first()?->id;

        return $tenantId === null ? null : (string) $tenantId;
    }

    /**
     * Check if user can manage any tenant.
     */
    protected function hasAnyTenant(User $user): bool
    {
        return $user->tenants()->exists();
    }

    /**
     * Standard view authorization - allows owner and admins.
     */
    protected function canView(User $user, Model $resource): bool
    {
        return $this->ownsResource($user, $resource) || $this->isAdmin($user);
    }

    /**
     * Standard update authorization - allows owner only.
     */
    protected function canUpdate(User $user, Model $resource): bool
    {
        return $this->ownsResource($user, $resource);
    }

    /**
     * Standard delete authorization - allows owner only.
     */
    protected function canDelete(User $user, Model $resource): bool
    {
        return $this->ownsResource($user, $resource);
    }

    /**
     * Check if user is a super admin (can access all tenants).
     */
    protected function isAdmin(User $user): bool
    {
        return $user->role === 'super_admin' || $user->hasRole('super_admin');
    }

    /**
     * Verify resource belongs to a specific tenant ID.
     * Useful when tenant_id comes from request parameter.
     */
    protected function resourceBelongsToTenant(Model $resource, string $tenantId): bool
    {
        if (! isset($resource->tenant_id)) {
            return false;
        }

        return (string) $resource->tenant_id === $tenantId;
    }
}
