<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Enums;

/**
 * Business Type Enum - Structural Discriminator
 *
 * Distinguishes between commerce (retail/inventory) and service (appointments/calendar)
 * business models to enable feature toggling at the tenant level.
 *
 * RETAIL: Traditional commerce with products, inventory, pickup/shipping
 * FOOD: Operational food businesses with kitchen/table workflows
 * SERVICE: Appointment-based businesses (salons, clinics, consultants)
 * HYBRID: Curated mix of retail + service without forcing food operations
 *
 * NOTE: Presentation concerns (labels, icons, descriptions) have been intentionally
 * removed from this Domain enum. They belong in the Filament Resource or a dedicated
 * presenter in the Infrastructure/Admin layer.
 */
enum BusinessType: string
{
    case RETAIL = 'retail';
    case FOOD = 'food';
    case SERVICE = 'service';
    case HYBRID = 'hybrid';
}
