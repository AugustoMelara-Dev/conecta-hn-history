<?php

declare(strict_types=1);

namespace App\Application\Sales\Commands;

final readonly class DetectAbandonedCartsCommand
{
    public function __construct(
        public int $timeoutMinutes = 30
    ) {}
}
