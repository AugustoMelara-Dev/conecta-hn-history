<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Enums;

/**
 * CommercialMode captures how a tenant wants to convert demand.
 *
 * business_type remains the structural compatibility layer; this enum drives
 * product positioning and capability decisions without forcing every vertical
 * into "catalog first".
 */
enum CommercialMode: string
{
    case PRODUCTS = 'products';
    case APPOINTMENTS = 'appointments';
    case CONSULTING_LEADS = 'consulting_leads';
    case HYBRID = 'hybrid';

    public function businessType(): BusinessType
    {
        return match ($this) {
            self::PRODUCTS => BusinessType::RETAIL,
            self::APPOINTMENTS,
            self::CONSULTING_LEADS,
            self::HYBRID => BusinessType::SERVICE,
        };
    }

    public static function safeFrom(?string $value, ?BusinessType $fallbackType = null): self
    {
        $mode = self::tryFrom((string) $value);

        if ($mode instanceof self) {
            return $mode;
        }

        return $fallbackType === BusinessType::SERVICE
            ? self::APPOINTMENTS
            : self::PRODUCTS;
    }
}
