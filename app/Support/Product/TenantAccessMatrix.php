<?php

declare(strict_types=1);

namespace App\Support\Product;

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\CommercialMode;
use App\Models\Tenant;
use Filament\Facades\Filament;

final class TenantAccessMatrix
{
    public const MODULE_PRODUCTS = 'products';

    public const MODULE_MENU_SECTIONS = 'menu_sections';

    public const MODULE_SERVICES = 'services';

    public const MODULE_SERVICE_PROVIDERS = 'service_providers';

    public const MODULE_APPOINTMENTS = 'appointments';

    public static function currentTenantCanAccess(string $module): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant && self::tenantCanAccess($tenant, $module);
    }

    public static function tenantCanAccess(Tenant $tenant, string $module): bool
    {
        return match ($module) {
            self::MODULE_PRODUCTS => self::sellsProducts($tenant),
            self::MODULE_MENU_SECTIONS => self::sellsProducts($tenant) && self::isPro($tenant),
            self::MODULE_SERVICES,
            self::MODULE_SERVICE_PROVIDERS => self::takesAppointments($tenant),
            self::MODULE_APPOINTMENTS => self::takesAppointments($tenant),
            default => false,
        };
    }

    private static function sellsProducts(Tenant $tenant): bool
    {
        $mode = self::commercialMode($tenant);

        if ($mode === CommercialMode::HYBRID) {
            return true;
        }

        if ($mode === CommercialMode::PRODUCTS) {
            return true;
        }

        return self::legacyBusinessType($tenant) === BusinessType::RETAIL
            && self::rawCommercialMode($tenant) === null;
    }

    private static function takesAppointments(Tenant $tenant): bool
    {
        $mode = self::commercialMode($tenant);

        if ($mode === CommercialMode::HYBRID) {
            return true;
        }

        if ($mode === CommercialMode::APPOINTMENTS) {
            return true;
        }

        return self::legacyBusinessType($tenant) === BusinessType::SERVICE
            && self::rawCommercialMode($tenant) === null;
    }

    private static function commercialMode(Tenant $tenant): CommercialMode
    {
        return CommercialMode::safeFrom(self::rawCommercialMode($tenant), self::legacyBusinessType($tenant));
    }

    private static function rawCommercialMode(Tenant $tenant): ?string
    {
        try {
            $raw = $tenant->getRawOriginal('commercial_mode') ?? $tenant->getAttribute('commercial_mode');
        } catch (\Throwable) {
            return null;
        }

        return $raw instanceof CommercialMode ? $raw->value : (is_string($raw) && $raw !== '' ? $raw : null);
    }

    private static function legacyBusinessType(Tenant $tenant): ?BusinessType
    {
        try {
            $raw = $tenant->getRawOriginal('business_type') ?? $tenant->getAttribute('business_type');
        } catch (\Throwable) {
            return null;
        }

        return $raw instanceof BusinessType ? $raw : BusinessType::tryFrom((string) $raw);
    }

    private static function isPro(Tenant $tenant): bool
    {
        return (bool) $tenant->is_pro;
    }
}
