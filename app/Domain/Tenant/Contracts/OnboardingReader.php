<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Contracts;

interface OnboardingReader
{
    /**
     * Get available cities for registration.
     */
    public function getAvailableCities(): array;

    /**
     * Get business categories for registration.
     */
    public function getBusinessCategories(): array;
}
