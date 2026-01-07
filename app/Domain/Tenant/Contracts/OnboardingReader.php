<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Contracts;

use Illuminate\Support\Collection;

interface OnboardingReader
{
    /**
     * Get available cities for registration.
     *
     * @return Collection
     */
    public function getAvailableCities(): Collection;

    /**
     * Get business categories for registration.
     *
     * @return Collection
     */
    public function getBusinessCategories(): Collection;
}
