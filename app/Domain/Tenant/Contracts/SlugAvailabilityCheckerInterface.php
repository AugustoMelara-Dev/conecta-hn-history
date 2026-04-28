<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Contracts;

interface SlugAvailabilityCheckerInterface
{
    public function isAvailable(string $slug): bool;
}
