<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Repositories;

use App\Application\Common\Integration\IntegrationEventMapper;
use App\Application\Tenancy\Interfaces\TenantRepositoryInterface;
use App\Domain\Common\Contracts\EventStoreRepositoryInterface;
use App\Domain\Common\Contracts\SnapshotRepositoryInterface;
use App\Domain\Common\Contracts\SnapshotStrategyInterface;
use App\Domain\Common\Exceptions\ConcurrencyException;
use App\Domain\TenantGovernance\Entities\TenantEntity;
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

    public function existsByDomain(string $domain): bool
    {
        // REAL SQL CHECK
        return DB::table('tenants')->where('domain', $domain)->exists();
    }

    public function save(TenantEntity $tenant): void
    {
        $events = method_exists($tenant, 'getUncommittedEvents') ? $tenant->getUncommittedEvents() : [];

        if (empty($events)) {
            return;
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
    }

    private function projectState(TenantEntity $tenant): void
    {
        DB::table('tenants')->upsert(
            [
                'id' => $tenant->id(),
                'name' => $tenant->name(),
                'domain' => $tenant->domain(),
                'created_at' => $tenant->createdAt(),
                'updated_at' => $tenant->updatedAt(),
                // Assumed fields based on SQL table definition
                'slug' => $tenant->domain(), // Mapping domain to slug if 1:1, or entity should expose slug
            ],
            ['id'],
            ['name', 'domain', 'updated_at']
        );
    }
}
