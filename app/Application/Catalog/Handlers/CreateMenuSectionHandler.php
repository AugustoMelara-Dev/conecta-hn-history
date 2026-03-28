<?php

declare(strict_types=1);

namespace App\Application\Catalog\Handlers;

use App\Application\Catalog\Commands\CreateMenuSectionCommand;
use App\Application\Catalog\Contracts\MenuSectionWriterInterface;
use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Models\MenuSection;

final readonly class CreateMenuSectionHandler
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private MenuSectionWriterInterface $menuSectionWriter,
    ) {}

    /**
     * @throws TenantNotFoundException
     */
    public function handle(CreateMenuSectionCommand $command): MenuSection
    {
        if ($this->tenantRepository->findById($command->tenantId) === null) {
            throw TenantNotFoundException::withId($command->tenantId);
        }

        return $this->menuSectionWriter->create($command->tenantId, [
            'name' => trim($command->name),
            'is_active' => $command->isActive,
            'sort_order' => $this->menuSectionWriter->getNextSortOrder($command->tenantId),
        ]);
    }
}
