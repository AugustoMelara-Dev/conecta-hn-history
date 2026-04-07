<?php

declare(strict_types=1);

namespace App\Application\Catalog\Handlers;

use App\Application\Catalog\Commands\UpdateMenuSectionCommand;
use App\Application\Catalog\Contracts\MenuSectionWriterInterface;
use App\Models\MenuSection;

final readonly class UpdateMenuSectionHandler
{
    public function __construct(
        private MenuSectionWriterInterface $menuSectionWriter,
    ) {}

    public function handle(UpdateMenuSectionCommand $command): MenuSection
    {
        $attributes = [];

        if ($command->name !== null) {
            $attributes['name'] = trim($command->name);
        }

        if ($command->isActive !== null) {
            $attributes['is_active'] = $command->isActive;
        }

        if ($attributes === []) {
            throw new \InvalidArgumentException('At least one menu section attribute must be provided.');
        }

        return $this->menuSectionWriter->update(
            $command->tenantId,
            $command->menuSectionId,
            $attributes,
        );
    }
}
