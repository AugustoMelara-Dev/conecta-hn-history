<?php

declare(strict_types=1);

namespace App\Application\Catalog\Contracts;

use App\Models\MenuSection;

interface MenuSectionWriterInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, array $attributes): MenuSection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $tenantId, int $menuSectionId, array $attributes): MenuSection;

    public function delete(string $tenantId, int $menuSectionId): void;

    /**
     * @param  array<int, int>  $orderedMenuSectionIds
     */
    public function reorder(string $tenantId, array $orderedMenuSectionIds): void;

    public function getNextSortOrder(string $tenantId): int;
}
