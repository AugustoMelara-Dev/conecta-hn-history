<?php

declare(strict_types=1);

namespace App\Domain\Public\Contracts;


/**
 * LandingPageRepository - Domain Contract for Landing Page Data
 *
 * Provides optimized read-only data for the homepage.
 */
interface LandingPageRepository
{
    /**
     * Get active categories for navigation.
     */
    public function getCategories(): array;

    /**
     * Get popular cities with tenant counts.
     */
    public function getPopularCities(): array;

    /**
     * Get latest approved tenants for social proof.
     */
    public function getLatestTenants(): array;

    /**
     * Get a random hero image from cities.
     */
    public function getRandomHeroImage(): ?string;

    /**
     * Get aggregate stats for trust badges.
     */
    public function getStats(): array;
}
