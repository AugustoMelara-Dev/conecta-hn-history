<?php

declare(strict_types=1);

namespace App\Application\Catalog\Commands;

final readonly class DeleteMenuSectionCommand
{
    public function __construct(
        public string $tenantId,
        public int $menuSectionId,
    ) {}
}
