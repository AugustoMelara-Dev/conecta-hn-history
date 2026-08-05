<?php

declare(strict_types=1);

namespace App\Application\Directory\Results;

final readonly class PublicTenantEngagementResult
{
    public function __construct(
        public string $tenantId,
        public bool $engaged,
        public int $count,
    ) {}
}
