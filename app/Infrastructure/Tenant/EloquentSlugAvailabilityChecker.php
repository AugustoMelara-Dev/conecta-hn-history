<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Domain\Tenant\Contracts\SlugAvailabilityCheckerInterface;
use App\Models\Tenant;

final class EloquentSlugAvailabilityChecker implements SlugAvailabilityCheckerInterface
{
    public function isAvailable(string $slug): bool
    {
        return ! Tenant::where('slug', $slug)->exists();
    }
}
