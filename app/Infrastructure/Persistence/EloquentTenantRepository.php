<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\Exceptions\StaleObjectException;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Models\Tenant as TenantModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class EloquentTenantRepository implements TenantRepositoryInterface
{
    public function findById(string $id): ?TenantEntity
    {
        try {
            /** @var TenantModel $model */
            $model = TenantModel::query()->findOrFail($id);

            return $this->toEntity($model);
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * @deprecated Use findById() for Snapshot Reads. Pessimistic Locking is BANNED for high-concurrency flows.
     */
    public function findByIdForUpdate(string $id): ?TenantEntity
    {
        try {
            /** @var TenantModel $model */
            $model = TenantModel::query()->lockForUpdate()->findOrFail($id);

            return $this->toEntity($model);
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    public function save(TenantEntity $tenant): TenantEntity
    {
        // Standard save (Last Write Wins) - Use only if concurrency is not a concern
        $model = TenantModel::query()->find($tenant->id());
        if (! $model) {
            $model = new TenantModel;
            $model->id = $tenant->id();
        }

        $this->hydrateModel($model, $tenant);
        $model->save();

        return $this->toEntity($model);
    }

    /**
     * ATOMIC OPTIMISTIC UPDATE
     * Securely updates the tenant ONLY if the version matches the snapshot.
     *
     * @throws StaleObjectException
     */
    public function updateOptimistically(TenantEntity $tenant): void
    {
        // 1. Prepare Update Query
        // SQL: UPDATE tenants SET plan_id = ?, ..., version = version + 1
        // WHERE id = ? AND version = ?

        $affected = TenantModel::query()
            ->where('id', $tenant->id())
            ->where('version', $tenant->version) // Optimistic Gate
            ->update([
                'plan_id' => $tenant->planId(),
                'subscription_ends_at' => $tenant->subscriptionEndsAt(),
                'subscription_reference' => $tenant->subscriptionReference(),
                'version' => $tenant->version + 1, // Atomic Increment in DB Engine
                'updated_at' => now(),
            ]);

        // 2. Verify Outcome
        if ($affected === 0) {
            // Case A: Row deleted
            // Case B: Version changed (Concurrency Conflict)

            // Check if it exists at all
            if (! TenantModel::query()->where('id', $tenant->id())->exists()) {
                throw TenantNotFoundException::withId($tenant->id());
            }

            // Must be a staleness issue
            throw StaleObjectException::forEntity('Tenant', $tenant->id());
        }

        // Success. Update local entity version to match DB if we were keeping it in memory (optional but good practice)
        // Note: Entities are immutable, so technically we can't update $tenant->version in place.
    }

    private function toEntity(TenantModel $model): TenantEntity
    {
        return new TenantEntity(
            id: (string) $model->id,
            planId: $model->plan_id,
            subscriptionEndsAt: $model->subscription_ends_at ? new \DateTimeImmutable($model->subscription_ends_at) : null,
            subscriptionReference: $model->subscription_reference,
            version: (int) $model->version // Load version from DB
        );
    }

    private function hydrateModel(TenantModel $model, TenantEntity $tenant): void
    {
        $model->plan_id = $tenant->planId();
        $model->subscription_ends_at = $tenant->subscriptionEndsAt();
        $model->subscription_reference = $tenant->subscriptionReference();
        // Do not manually set version here for save(), only for updateOptimistically
    }

    public function findBySlug(string $slug): ?TenantEntity
    {
        /** @var TenantModel|null $model */
        $model = TenantModel::query()->where('slug', $slug)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function existsBySlug(string $slug): bool
    {
        return TenantModel::query()->where('slug', $slug)->exists();
    }

    public function findByDomain(string $domain): ?TenantEntity
    {
        /** @var TenantModel|null $model */
        $model = TenantModel::query()->where('domain', $domain)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): TenantEntity
    {
        /** @var TenantModel $model */
        $model = TenantModel::query()->create($attributes);

        return $this->toEntity($model);
    }

    public function delete(TenantEntity $tenant): void
    {
        TenantModel::query()->where('id', $tenant->id())->delete();
    }
}
