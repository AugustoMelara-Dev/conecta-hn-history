<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

use Illuminate\Support\Collection;

/**
 * CityReader - Port for City Data Access
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE:                                               ║
 * ║  - DIP: Controllers depend on this interface, not Eloquent              ║
 * ║  - SRP: Read-only operations for city data                              ║
 * ║  - Testability: Can be mocked in unit tests                             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface CityReader
{
    /**
     * Get all cities with their geographic hierarchy.
     *
     * @return Collection<int, object> Collection of city DTOs or stdClass objects
     */
    public function getAllCities(): Collection;
}
