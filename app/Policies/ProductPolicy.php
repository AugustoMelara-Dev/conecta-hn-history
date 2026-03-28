<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;

class ProductPolicy extends TenantAwarePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $this->isAdmin($user) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Capability::CATALOG_BASIC, Permission::CATALOG_VIEW);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->allows($user, Capability::CATALOG_BASIC, Permission::CATALOG_VIEW, $product);
    }

    public function create(User $user): Response
    {
        $tenant = $this->resolveTenant($user);

        if (! $tenant) {
            return Response::deny('Debes tener un negocio para crear productos.');
        }

        if (! $this->allows($user, Capability::CATALOG_BASIC, Permission::CATALOG_CREATE)) {
            return Response::deny('No tienes permiso para crear productos en este negocio.');
        }

        $settings = app(GeneralSettings::class);
        $limit = $this->isPaidPlan($tenant)
            ? $settings->pro_plan_product_limit
            : $settings->free_plan_product_limit;

        $currentCount = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->count();

        if ($currentCount < $limit) {
            return Response::allow();
        }

        $planName = $this->isPaidPlan($tenant) ? $this->planLabel($tenant) : 'Gratuito';
        $upgradePrompt = $this->isPaidPlan($tenant)
            ? 'Contacta a soporte para aumentar tu capacidad.'
            : 'Actualiza a PRO para expandir tu catalogo.';

        return Response::deny(
            "Has alcanzado el limite de {$limit} productos de tu Plan {$planName}. {$upgradePrompt}"
        );
    }

    public function update(User $user, Product $product): bool
    {
        return $this->allows($user, Capability::CATALOG_BASIC, Permission::CATALOG_UPDATE, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->allows($user, Capability::CATALOG_BASIC, Permission::CATALOG_DELETE, $product);
    }

    public function restore(User $user, Product $product): bool
    {
        return $this->allows($user, Capability::CATALOG_BASIC, Permission::CATALOG_UPDATE, $product);
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $this->allows($user, Capability::CATALOG_BASIC, Permission::CATALOG_DELETE, $product)
            && $this->isTenantOwner($user, $this->resolveTenant($user, $product));
    }

    private function isPaidPlan(Tenant $tenant): bool
    {
        $planKey = $tenant->getRawOriginal('plan_type') ?: ($tenant->getAttributes()['plan_type'] ?? null);

        return match (true) {
            $planKey instanceof TenantPlan => $planKey !== TenantPlan::SEMILLA,
            is_string($planKey) => $planKey !== '' && $planKey !== Tenant::PLAN_SEMILLA,
            default => false,
        };
    }

    private function planLabel(Tenant $tenant): string
    {
        $planKey = $tenant->getRawOriginal('plan_type') ?: ($tenant->getAttributes()['plan_type'] ?? null);
        $value = match (true) {
            $planKey instanceof TenantPlan => $planKey->value,
            is_string($planKey) => $planKey,
            default => Tenant::PLAN_SEMILLA,
        };

        return match ($value) {
            Tenant::PLAN_CRECIMIENTO => 'Crecimiento',
            Tenant::PLAN_COSECHA => 'Cosecha',
            default => 'PRO',
        };
    }
}
