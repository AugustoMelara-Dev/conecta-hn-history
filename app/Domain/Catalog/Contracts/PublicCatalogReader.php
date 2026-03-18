<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Contracts;

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
    public function getFeaturedProducts(int $limit = 6): array;

    /**
     * Get all active locations (tenants) with coordinates.
     */
    public function getLocations(): array;

    /**
     * Get directory listing.
     */
    public function getDirectoryListing(): array;
}
