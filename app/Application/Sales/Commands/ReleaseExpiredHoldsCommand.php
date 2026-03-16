<?php

declare(strict_types=1);

namespace App\Application\Sales\Commands;

final readonly class ReleaseExpiredHoldsCommand
{
    public function __construct(
        public ?string $tenantId = null,
    ) {}
}
