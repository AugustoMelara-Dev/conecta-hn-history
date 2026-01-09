<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Contracts;

use Illuminate\Support\Collection;

/**
 * PublicCatalogReader - Data Access for Public Pages
 *
 * Responsibility: Fetch data for public landing pages, directories, and maps.
 */
interface PublicCatalogReader
{
    /**
     * Get featured products for the welcome page.
     */
    public function getFeaturedProducts(int $limit = 6): Collection;

    /**
     * Get all active locations (tenants) with coordinates.
     */
    public function getLocations(): Collection;

    /**
     * Get directory listing.
     * 
     * @return Collection
     */
    public function getDirectoryListing(): Collection;
}
