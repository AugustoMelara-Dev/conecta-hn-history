<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Business Type Enum - Structural Discriminator
 * 
 * Distinguishes between commerce (retail/inventory) and service (appointments/calendar)
 * business models to enable feature toggling at the tenant level.
 * 
 * RETAIL: Traditional commerce with products, inventory, POS
 * SERVICE: Appointment-based businesses (salons, clinics, consultants)
 */
enum BusinessType: string implements HasLabel
{
    case RETAIL = 'retail';
    case SERVICE = 'service';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::RETAIL => 'Comercio (Retail)',
            self::SERVICE => 'Servicios / Reservas',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::RETAIL => 'heroicon-o-shopping-bag',
            self::SERVICE => 'heroicon-o-calendar',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::RETAIL => 'Tiendas, restaurantes, comercios con inventario y productos',
            self::SERVICE => 'Clínicas, salones, consultorías con citas y reservaciones',
        };
    }
}
