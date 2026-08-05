<?php

declare(strict_types=1);

namespace App\Application\Availability;

use Carbon\CarbonImmutable;

final readonly class AvailabilityDecision
{
    public function __construct(
        public bool $available,
        public string $reason,
        public CarbonImmutable $startsAtUtc,
        public CarbonImmutable $endsAtUtc,
        public string $timezone,
    ) {}
}
