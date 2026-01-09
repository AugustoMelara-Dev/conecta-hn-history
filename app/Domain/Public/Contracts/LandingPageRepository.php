<?php

declare(strict_types=1);

namespace App\Domain\Public\Contracts;

use Illuminate\Support\Collection;

/**
 * LandingPageRepository - Domain Contract for Landing Page Data
 *
 * Provides optimized read-only data for the homepage.
 */
interface LandingPageRepository
{
    /**
     * Get active categories for navigation.
     * @return Collection
     */
    public function getCategories(): Collection;

    /**
     * Get popular cities with tenant counts.
     * @return Collection
     */
    public function getPopularCities(): Collection;

    /**
     * Get latest approved tenants for social proof.
     * @return Collection
     */
    public function getLatestTenants(): Collection;

    /**
     * Get a random hero image from cities.
     * @return string|null
     */
    public function getRandomHeroImage(): ?string;

    /**
     * Get aggregate stats for trust badges.
     * @return array
     */
    public function getStats(): array;
}
