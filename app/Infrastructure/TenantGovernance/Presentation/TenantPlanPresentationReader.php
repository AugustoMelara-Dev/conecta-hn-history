<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Presentation;

use App\Domain\TenantGovernance\Contracts\PlanLimitsInterface;
use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final readonly class TenantPlanPresentationReader
{
    public function __construct(
        private PlanLimitsInterface $planLimits,
    ) {}

    /**
     * @return array{
     *   key:string,
     *   label:string,
     *   marketingLabel:string,
     *   billingCta:string,
     *   priceLabel:string,
     *   isUpgradeable:bool,
     *   isCatalogUnlimited:bool,
     *   hasMarketingSuite:bool,
     *   hasAdvancedBranding:bool,
     *   hasFollowerInsights:bool,
     *   productLimit:int|null,
     *   productLimitLabel:string,
     *   productSlotsRemaining:int|null,
     *   catalogCopy:string,
     *   catalogStatusCopy:string,
     *   marketingCopy:string,
     *   brandingCopy:string,
     *   followersCopy:string
     * }
     */
    public function forTenant(Tenant $tenant): array
    {
        $isUpgradeable = $tenant->hasFeature(TenantFeature::UPGRADE_PROMPT);
        $isCatalogUnlimited = $tenant->hasFeature(TenantFeature::ADVANCED_CATALOG);
        $hasMarketingSuite = $tenant->hasFeature(TenantFeature::MARKETING_SUITE);
        $hasAdvancedBranding = $tenant->hasFeature(TenantFeature::ADVANCED_BRANDING);
        $hasFollowerInsights = $tenant->hasFeature(TenantFeature::MARKETING_SUITE);

        $planKey = $isUpgradeable ? 'semilla' : 'pro';
        $productLimit = $isCatalogUnlimited ? null : $this->resolveProductLimit($tenant, $planKey);
        $productCount = (int) DB::table('products')
            ->where('tenant_id', $tenant->getKey())
            ->whereNull('deleted_at')
            ->count();
        $productSlotsRemaining = $productLimit === null ? null : max(0, $productLimit - $productCount);

        return [
            'key' => $planKey,
            'label' => $isUpgradeable ? 'Base' : 'PRO',
            'marketingLabel' => $isUpgradeable ? 'Plan Base' : 'Plan PRO',
            'billingCta' => $isUpgradeable ? 'Activar PRO' : 'Gestionar plan',
            'priceLabel' => 'L. '.number_format($this->planLimits->getPlanPrice('pro'), 2).'/mes',
            'isUpgradeable' => $isUpgradeable,
            'isCatalogUnlimited' => $isCatalogUnlimited,
            'hasMarketingSuite' => $hasMarketingSuite,
            'hasAdvancedBranding' => $hasAdvancedBranding,
            'hasFollowerInsights' => $hasFollowerInsights,
            'productLimit' => $productLimit,
            'productLimitLabel' => $productLimit === null ? 'Catalogo ampliado' : "{$productLimit} productos",
            'productSlotsRemaining' => $productSlotsRemaining,
            'catalogCopy' => $isCatalogUnlimited
                ? 'Catalogo ampliado para publicar mas productos, imagenes y merchandising.'
                : 'Modo esencial para operar con un catálogo acotado mientras validas tracción.',
            'catalogStatusCopy' => $this->catalogStatusCopy($productCount, $productLimit),
            'marketingCopy' => $hasMarketingSuite
                ? 'Suite comercial activa para QR, WhatsApp, campañas y seguimiento.'
                : 'Activa PRO para desbloquear QR, campañas y una narrativa comercial más fuerte.',
            'brandingCopy' => $hasAdvancedBranding
                ? 'Personalizacion visual avanzada ya habilitada para tu micrositio.'
                : 'Activa PRO para desbloquear branding avanzado, portada y una presencia más sólida.',
            'followersCopy' => $hasFollowerInsights
                ? 'Tu audiencia ya esta disponible con detalle completo para operar seguimiento.'
                : 'En el plan Base mantienes el conteo general. El detalle completo se habilita al activar PRO.',
        ];
    }

    private function resolveProductLimit(Tenant $tenant, string $planKey): int
    {
        $explicitLimit = $tenant->getAttribute('product_limit');

        if (is_numeric($explicitLimit)) {
            return (int) $explicitLimit;
        }

        return $this->planLimits->getProductLimit($planKey);
    }

    private function catalogStatusCopy(int $productCount, ?int $productLimit): string
    {
        if ($productLimit === null) {
            return 'Tu catalogo ya puede crecer sin toparse con el limite del plan Base.';
        }

        if ($productLimit <= 0) {
            return 'El catalogo necesita una configuracion valida de limite.';
        }

        $usage = (int) round(($productCount / $productLimit) * 100);

        return match (true) {
            $usage >= 100 => 'Has alcanzado el limite del plan Base. Activa PRO para seguir publicando.',
            $usage >= 80 => "Estas usando el {$usage}% del limite de catalogo del plan Base.",
            $usage >= 50 => 'Estas aprovechando bien tu capacidad actual. Conviene preparar el upgrade antes del tope.',
            default => 'Tienes espacio suficiente para seguir ampliando tu catalogo.',
        };
    }
}
