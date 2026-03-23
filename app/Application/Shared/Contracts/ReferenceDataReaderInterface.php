<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * ReferenceDataReaderInterface - Read-Only Lookup Data Port
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURPOSE: Provides access to reference/lookup data without Eloquent.    ║
 * ║  READ-ONLY: No mutations allowed through this interface.                ║
 * ║  DECOUPLED: Controllers use this instead of Model::where()              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface ReferenceDataReaderInterface
{
    /**
     * Get all available cities for registration forms.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function getAvailableCities(): array;

    /**
     * Get all active categories for registration forms.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function getActiveCategories(): array;

    /**
     * Get highlighted categories for public discovery surfaces.
     *
     * @return array<int, array{id: int, name: string, slug: string, icon: string|null}>
     */
    public function getPopularCategories(int $limit = 8): array;

    /**
     * Get highlighted cities for public discovery surfaces.
     *
     * @return array<int, array{id: int, name: string, slug: string, hero_image: string|null, department: string|null, tenants_count: int}>
     */
    public function getPopularCities(int $limit = 4): array;

    /**
     * Count cities available for public discovery.
     */
    public function countActiveCities(): int;
}
