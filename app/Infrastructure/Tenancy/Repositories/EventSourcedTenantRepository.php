<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Repositories;

use App\Application\Shared\Common\Integration\IntegrationEventMapper;
use App\Domain\Common\Contracts\EventStoreRepositoryInterface;
use App\Domain\Common\Contracts\SnapshotRepositoryInterface;
use App\Domain\Common\Contracts\SnapshotStrategyInterface;
use App\Domain\Common\Exceptions\ConcurrencyException;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Common\Outbox\OutboxRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * EventSourcedTenantRepository - Hybrid Persistence
 *
 * - Inline Projection to 'tenants'.
 * - Efficient SQL checks.
 */
final class EventSourcedTenantRepository implements TenantRepositoryInterface
{
    public function __construct(
        private EventStoreRepositoryInterface $eventStore,
        private OutboxRepository $outbox,
        private SnapshotRepositoryInterface $snapshotRepo,
        private SnapshotStrategyInterface $snapshotStrategy,
        private IntegrationEventMapper $mapper
    ) {}

    public function findById(string $tenantId): ?TenantEntity
    {
        $eventStream = $this->eventStore->getEventsFrom($tenantId, 0);
        $events = iterator_to_array($eventStream);

        if (empty($events)) {
            return null;
        }

        return TenantEntity::reconstituteFromHistory($events);
    }

    public function findByIdForUpdate(string $id): ?TenantEntity
    {
        return $this->findById($id);
    }

    public function findBySlug(string $slug): ?TenantEntity
    {
        $id = DB::table('tenants')->where('slug', $slug)->value('id');

        return $id ? $this->findById($id) : null;
    }

    public function existsBySlug(string $slug): bool
    {
        return DB::table('tenants')->where('slug', $slug)->exists();
    }

    public function findByDomain(string $domain): ?TenantEntity
    {
        // Domain is mapped to slug in this architecture
        return $this->findBySlug($domain);
    }

    public function create(array $attributes): TenantEntity
    {
        throw new \BadMethodCallException('EventSourced repository does not support array-based create. Use save() with new Entity.');
    }

    public function delete(TenantEntity $tenant): void
    {
        // To be implemented: Soft delete event or similar
    }

    public function save(TenantEntity $tenant): TenantEntity
    {
        $events = method_exists($tenant, 'getUncommittedEvents') ? $tenant->getUncommittedEvents() : [];

        if (empty($events)) {
            return $tenant;
        }

        try {
            $this->eventStore->append(
                tenantId: $tenant->id(),
                aggregateId: $tenant->id(),
                aggregateType: 'Tenant',
                events: $events,
                expectedVersion: $tenant->version()
            );
        } catch (QueryException $e) {
            throw new ConcurrencyException('Tenant persistence failed.', 0, $e);
        }

        $outboxMessages = $this->mapper->map($events);

        if (! empty($outboxMessages)) {
            $this->outbox->append($outboxMessages);
        }

        // INLINE PROJECTION
        $this->projectState($tenant);

        if ($this->snapshotStrategy->shouldSnapshot($tenant)) {
            $this->snapshotRepo->save(
                $tenant->id(),
                $tenant->version(),
                $tenant->toStateArray()
            );
        }

        return $tenant;
    }

    private function projectState(TenantEntity $tenant): void
    {
        DB::table('tenants')->upsert(
            [
                'id' => $tenant->id(),
                'name' => $tenant->name(),
                // 'domain' column does not exist
                'created_at' => $tenant->createdAt(),
                'updated_at' => $tenant->updatedAt(),
                'slug' => $tenant->domain(), // Mapping domain to slug
            ],
            ['id'],
            ['name', 'slug', 'updated_at'] // Update slug instead of domain
        );
    }
}
