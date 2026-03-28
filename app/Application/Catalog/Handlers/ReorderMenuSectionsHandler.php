<?php

declare(strict_types=1);

namespace App\Application\Catalog\Handlers;

use App\Application\Catalog\Commands\ReorderMenuSectionsCommand;
use App\Application\Catalog\Contracts\MenuSectionWriterInterface;

final readonly class ReorderMenuSectionsHandler
{
    public function __construct(
        private MenuSectionWriterInterface $menuSectionWriter,
    ) {}

    public function handle(ReorderMenuSectionsCommand $command): void
    {
        $orderedMenuSectionIds = [];

        foreach ($command->orderedMenuSectionIds as $menuSectionId) {
            $menuSectionId = (int) $menuSectionId;

            if ($menuSectionId <= 0 || in_array($menuSectionId, $orderedMenuSectionIds, true)) {
                continue;
            }

            $orderedMenuSectionIds[] = $menuSectionId;
        }

        if ($orderedMenuSectionIds === []) {
            return;
        }

        $this->menuSectionWriter->reorder($command->tenantId, $orderedMenuSectionIds);
    }
}
