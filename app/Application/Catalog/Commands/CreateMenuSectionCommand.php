<?php

declare(strict_types=1);

namespace App\Application\Catalog\Commands;

final readonly class CreateMenuSectionCommand
{
    public function __construct(
        public string $tenantId,
        public string $name,
        public bool $isActive = true,
    ) {}
}
