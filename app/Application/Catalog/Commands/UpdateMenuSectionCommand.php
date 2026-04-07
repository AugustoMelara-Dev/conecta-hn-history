<?php

declare(strict_types=1);

namespace App\Application\Catalog\Commands;

final readonly class UpdateMenuSectionCommand
{
    public function __construct(
        public string $tenantId,
        public int $menuSectionId,
        public ?string $name = null,
        public ?bool $isActive = null,
    ) {}
}
