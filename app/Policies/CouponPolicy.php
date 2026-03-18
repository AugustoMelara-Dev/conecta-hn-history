<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Coupon;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * CouponPolicy - Tenant-Aware Authorization for Coupons
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * SECURITY: Enforces tenant ownership for promotional discount management
 * ══════════════════════════════════════════════════════════════════════════════
 */
class CouponPolicy extends TenantAwarePolicy
{
    /**
     * Determine whether the user can view any coupons.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    /**
     * Determine whether the user can view the coupon.
     */
    public function view(User $user, Coupon $coupon): bool
    {
        return $this->ownsResource($user, $coupon);
    }

    /**
     * Determine whether the user can create coupons.
     */
    public function create(User $user): Response
    {
        $tenant = \Filament\Facades\Filament::getTenant()
            ?? $user->tenants()->first();

        if (! $tenant) {
            return Response::deny('Debes tener un negocio para crear cupones.');
        }

        if (! $tenant instanceof Tenant) {
            return Response::deny('No se pudo resolver el contexto del negocio.');
        }

        // Only PRO tenants can create coupons
        if (! $tenant->is_pro) {
            return Response::deny(
                'Los cupones de descuento son una característica PRO. '.
                'Actualiza tu plan para crear promociones.'
            );
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update the coupon.
     */
    public function update(User $user, Coupon $coupon): bool
    {
        return $this->ownsResource($user, $coupon);
    }

    /**
     * Determine whether the user can delete the coupon.
     */
    public function delete(User $user, Coupon $coupon): bool
    {
        return $this->ownsResource($user, $coupon);
    }

    /**
     * Determine whether the user can restore the coupon.
     */
    public function restore(User $user, Coupon $coupon): bool
    {
        return $this->ownsResource($user, $coupon);
    }

    /**
     * Determine whether the user can permanently delete the coupon.
     */
    public function forceDelete(User $user, Coupon $coupon): bool
    {
        return $this->ownsResource($user, $coupon) && $this->isAdmin($user);
    }
}
