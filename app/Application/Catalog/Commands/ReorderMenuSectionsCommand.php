<?php

declare(strict_types=1);

namespace App\Application\Catalog\Commands;

final readonly class ReorderMenuSectionsCommand
{
    /**
     * @param  array<int, int>  $orderedMenuSectionIds
     */
    public function __construct(
        public string $tenantId,
        public array $orderedMenuSectionIds,
    ) {}
}
