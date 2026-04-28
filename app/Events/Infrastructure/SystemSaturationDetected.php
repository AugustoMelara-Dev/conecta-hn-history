<?php

declare(strict_types=1);

namespace App\Events\Infrastructure;

final readonly class SystemSaturationDetected
{
    public function __construct(
        public string $component,
        public int $partition,
        public float $lagSeconds,
        public int $thresholdSeconds,
    ) {}
}
