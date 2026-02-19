<?php

declare(strict_types=1);

namespace App\Contracts\Scope;

/**
 * TenantContextInterface - Domain Port for Tenant Resolution
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HEXAGONAL ARCHITECTURE: INBOUND PORT                                   ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  This interface represents the Domain's need to know "who is the        ║
 * ║  current tenant owner" WITHOUT knowing WHERE that information comes     ║
 * ║  from. The Domain declares the contract; Infrastructure implements it.  ║
 * ║                                                                          ║
 * ║  IMPLEMENTATIONS:                                                        ║
 * ║    - FilamentTenantContext: For Filament Admin Panel requests           ║
 * ║    - ApiTenantContext: For API requests with tenant header              ║
 * ║    - ConsoleTenantContext: For Artisan commands with --tenant option    ║
 * ║    - TestTenantContext: For unit tests with explicit tenant injection   ║
 * ║                                                                          ║
 * ║  DEPENDENCY INVERSION PRINCIPLE (SOLID):                                ║
 * ║    High-level modules (Domain) should not depend on low-level modules   ║
 * ║    (Filament). Both should depend on abstractions (this interface).     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface TenantContextInterface
{
    /**
     * Resolve the current tenant ID from the execution context.
     *
     * @return string|int|null The tenant ID, or null if no tenant context is available
     */
    public function getTenantId(): string|int|null;
}
