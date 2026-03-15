<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Auth\Access\Response;

/**
 * ProductPolicy - Tenant-Aware Authorization for Products
 *
 * SECURITY: Enforces tenant ownership for all product operations.
 */
class ProductPolicy extends TenantAwarePolicy
{
    /**
     * Determine whether the user can view any products.
     *
     * ALLOWED: Tenant owners can view their product list
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    /**
     * Determine whether the user can view the product.
     *
     * SECURITY: Only owner tenant can view
     */
    public function view(User $user, Product $product): bool
    {
        return $this->ownsResource($user, $product);
    }

    /**
     * Determine whether the user can create products.
     *
     * SECURITY: User must have a tenant + respect product limits
     */
    public function create(User $user): Response
    {
        // Get user's primary tenant from Filament context or relationship
        $tenant = \Filament\Facades\Filament::getTenant()
            ?? $user->tenants()->first();

        if (! $tenant) {
            return Response::deny('Debes tener un negocio para crear productos.');
        }

        $settings = app(GeneralSettings::class);
        $limit = $tenant->is_pro
            ? $settings->pro_plan_product_limit
            : $settings->free_plan_product_limit;

        if ($tenant->products()->count() < $limit) {
            return Response::allow();
        }

        $planName = $tenant->is_pro ? 'PRO' : 'Gratuito';
        $upgradePrompt = $tenant->is_pro
            ? 'Contacta a soporte para aumentar tu capacidad.'
            : 'Actualiza a PRO para expandir tu catalogo.';

        return Response::deny(
            "Has alcanzado el límite de {$limit} productos de tu Plan {$planName}. {$upgradePrompt}"
        );
    }

    /**
     * Determine whether the user can update the product.
     *
     * SECURITY: Only owner tenant can update
     */
    public function update(User $user, Product $product): bool
    {
        return $this->ownsResource($user, $product);
    }

    /**
     * Determine whether the user can delete the product.
     *
     * SECURITY: Only owner tenant can delete
     */
    public function delete(User $user, Product $product): bool
    {
        return $this->ownsResource($user, $product);
    }

    /**
     * Determine whether the user can restore the product.
     */
    public function restore(User $user, Product $product): bool
    {
        return $this->ownsResource($user, $product);
    }

    /**
     * Determine whether the user can permanently delete the product.
     *
     * CAUTION: Destructive operation - stricter check
     */
    public function forceDelete(User $user, Product $product): bool
    {
        // Only allow if owner AND admin role
        return $this->ownsResource($user, $product) && $this->isAdmin($user);
    }
}
