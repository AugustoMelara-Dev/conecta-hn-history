<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

use App\Application\Catalog\Contracts\MenuSectionWriterInterface;
use App\Domain\Catalog\Exceptions\MenuSectionNotFoundException;
use App\Models\MenuSection;
use Illuminate\Support\Facades\DB;

final class EloquentMenuSectionWriter implements MenuSectionWriterInterface
{
    /**
     * @var list<string>
     */
    private const CREATABLE_COLUMNS = [
        'name',
        'is_active',
        'sort_order',
    ];

    /**
     * @var list<string>
     */
    private const UPDATABLE_COLUMNS = [
        'name',
        'is_active',
        'sort_order',
    ];

    public function create(string $tenantId, array $attributes): MenuSection
    {
        $menuSection = new MenuSection;
        $menuSection->forceFill([
            'tenant_id' => $tenantId,
            ...$this->onlyAllowed($attributes, self::CREATABLE_COLUMNS),
            'sort_order' => (int) ($attributes['sort_order'] ?? $this->getNextSortOrder($tenantId)),
        ]);
        $menuSection->save();

        return $menuSection->fresh() ?? $menuSection;
    }

    public function update(string $tenantId, int $menuSectionId, array $attributes): MenuSection
    {
        $menuSection = $this->findOwnedOrFail($tenantId, $menuSectionId);
        $payload = $this->onlyAllowed($attributes, self::UPDATABLE_COLUMNS);

        if ($payload === []) {
            return $menuSection;
        }

        $menuSection->forceFill($payload);
        $menuSection->save();

        return $menuSection->fresh() ?? $menuSection;
    }

    public function delete(string $tenantId, int $menuSectionId): void
    {
        DB::transaction(function () use ($tenantId, $menuSectionId): void {
            $menuSection = $this->findOwnedOrFail($tenantId, $menuSectionId);
            $menuSection->delete();

            $this->normalizeSortOrder($tenantId);
        });
    }

    public function reorder(string $tenantId, array $orderedMenuSectionIds): void
    {
        DB::transaction(function () use ($tenantId, $orderedMenuSectionIds): void {
            $existingIds = MenuSection::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $existingLookup = array_fill_keys($existingIds, true);

            foreach ($orderedMenuSectionIds as $menuSectionId) {
                if (! isset($existingLookup[$menuSectionId])) {
                    throw MenuSectionNotFoundException::withId($menuSectionId);
                }
            }

            $reorderedLookup = array_fill_keys($orderedMenuSectionIds, true);
            $remainingIds = array_values(array_filter(
                $existingIds,
                static fn (int $menuSectionId): bool => ! isset($reorderedLookup[$menuSectionId]),
            ));

            $this->applyOrder($tenantId, array_merge($orderedMenuSectionIds, $remainingIds));
        });
    }

    public function getNextSortOrder(string $tenantId): int
    {
        return ((int) MenuSection::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->max('sort_order')) + 1;
    }

    private function normalizeSortOrder(string $tenantId): void
    {
        $orderedIds = MenuSection::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $this->applyOrder($tenantId, $orderedIds);
    }

    /**
     * @param  array<int, int>  $orderedMenuSectionIds
     */
    private function applyOrder(string $tenantId, array $orderedMenuSectionIds): void
    {
        if ($orderedMenuSectionIds === []) {
            return;
        }

        $menuSections = MenuSection::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $orderedMenuSectionIds)
            ->get()
            ->keyBy(static fn (MenuSection $menuSection): int => (int) $menuSection->getKey());

        foreach ($orderedMenuSectionIds as $index => $menuSectionId) {
            $menuSection = $menuSections->get($menuSectionId);

            if (! $menuSection instanceof MenuSection) {
                throw MenuSectionNotFoundException::withId($menuSectionId);
            }

            $sortOrder = $index + 1;

            if ((int) $menuSection->sort_order === $sortOrder) {
                continue;
            }

            $menuSection->forceFill(['sort_order' => $sortOrder]);
            $menuSection->save();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $allowedColumns
     * @return array<string, mixed>
     */
    private function onlyAllowed(array $attributes, array $allowedColumns): array
    {
        $payload = [];

        foreach ($allowedColumns as $column) {
            if (! array_key_exists($column, $attributes)) {
                continue;
            }

            $payload[$column] = $attributes[$column];
        }

        return $payload;
    }

    private function findOwnedOrFail(string $tenantId, int $menuSectionId): MenuSection
    {
        $menuSection = MenuSection::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($menuSectionId)
            ->first();

        if (! $menuSection instanceof MenuSection) {
            throw MenuSectionNotFoundException::withId($menuSectionId);
        }

        return $menuSection;
    }
}
