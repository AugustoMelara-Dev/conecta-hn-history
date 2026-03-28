<?php

declare(strict_types=1);

namespace App\Application\Catalog\Handlers;

use App\Application\Catalog\Commands\DeleteMenuSectionCommand;
use App\Application\Catalog\Contracts\MenuSectionWriterInterface;

final readonly class DeleteMenuSectionHandler
{
    public function __construct(
        private MenuSectionWriterInterface $menuSectionWriter,
    ) {}

    public function handle(DeleteMenuSectionCommand $command): void
    {
        $this->menuSectionWriter->delete($command->tenantId, $command->menuSectionId);
    }
}
