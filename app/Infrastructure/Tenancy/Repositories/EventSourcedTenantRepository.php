<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Repositories;

use App\Application\Shared\Common\Integration\IntegrationEventMapper;
use App\Domain\Common\Contracts\EventStoreRepositoryInterface;
use App\Domain\Common\Contracts\SnapshotRepositoryInterface;
use App\Domain\Common\Contracts\SnapshotStrategyInterface;
use App\Domain\Common\Exceptions\ConcurrencyException;
use App\Domain\Shared\Exceptions\StaleObjectException;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Common\Outbox\OutboxRepository;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\OpeningHours\OpeningHours;

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

    public function getFeatured(int $limit = 12): array
    {
        return Tenant::query()
            ->published()
            ->inPriorityOrder()
            ->take($limit)
            ->get([
                'id',
                'name',
                'slug',
                'description',
                'hero_image',
                'cover_path',
                'brand_color',
                'plan_type',
                'is_pro',
            ])
            ->toArray();
    }

    public function countActive(): int
    {
        return Tenant::query()
            ->published()
            ->count();
    }

    public function getOpeningHours(string $tenantId): OpeningHours
    {
        $tenant = Tenant::find($tenantId);

        return $tenant?->getOpeningHours() ?? OpeningHours::create([
            'monday' => ['09:00-17:00'],
            'tuesday' => ['09:00-17:00'],
            'wednesday' => ['09:00-17:00'],
            'thursday' => ['09:00-17:00'],
            'friday' => ['09:00-17:00'],
        ]);
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
        $events = $tenant->getUncommittedEvents();

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

    public function updateOptimistically(TenantEntity $tenant): void
    {
        $state = $tenant->toStateArray();
        $affected = DB::table('tenants')
            ->where('id', $tenant->id())
            ->where('version', max(1, $tenant->version()))
            ->update([
                'user_id' => $state['owner_id'] ?? null,
                'name' => $state['name'],
                'slug' => $state['domain'],
                'is_active' => $state['is_active'],
                'category_id' => $state['category_id'] ?? null,
                'city_id' => $state['city_id'] ?? null,
                'logo_path' => $state['logo_path'] ?? null,
                'phone' => $state['phone'] ?? null,
                'address' => $state['address'] ?? null,
                'latitude' => $state['latitude'] ?? null,
                'longitude' => $state['longitude'] ?? null,
                'description' => $state['description'] ?? null,
                'slogan' => $state['slogan'] ?? null,
                'whatsapp_number' => $state['whatsapp'] ?? null,
                'is_ai_enhanced' => $state['is_ai_enhanced'] ?? false,
                'is_pro' => $state['is_pro'] ?? false,
                'product_limit' => $state['product_limit'] ?? 10,
                'plan_type' => $state['plan_id'] ?? TenantPlan::SEMILLA->value,
                'subscription_status' => $state['subscription_status'] ?? Tenant::DEFAULT_SUBSCRIPTION_STATUS,
                'subscription_ends_at' => $state['subscription_ends_at'] ?? null,
                'subscription_reference' => $state['subscription_reference'] ?? null,
                'updated_at' => $tenant->updatedAt(),
                'version' => DB::raw('version + 1'),
            ]);

        if ($affected === 0) {
            throw new StaleObjectException("Tenant {$tenant->id()} was modified by another process.");
        }
    }

    private function projectState(TenantEntity $tenant): void
    {
        $state = $tenant->toStateArray();

        DB::table('tenants')->upsert(
            [
                'id' => $tenant->id(),
                'name' => $tenant->name(),
                'created_at' => $tenant->createdAt(),
                'updated_at' => $tenant->updatedAt(),
                'slug' => $tenant->domain(),
                'user_id' => $state['owner_id'] ?? null,
                'is_active' => $state['is_active'],
                'category_id' => $state['category_id'] ?? null,
                'city_id' => $state['city_id'] ?? null,
                'logo_path' => $state['logo_path'] ?? null,
                'phone' => $state['phone'] ?? null,
                'address' => $state['address'] ?? null,
                'latitude' => $state['latitude'] ?? null,
                'longitude' => $state['longitude'] ?? null,
                'description' => $state['description'] ?? null,
                'slogan' => $state['slogan'] ?? null,
                'whatsapp_number' => $state['whatsapp'] ?? null,
                'is_ai_enhanced' => $state['is_ai_enhanced'] ?? false,
                'is_pro' => $state['is_pro'] ?? false,
                'product_limit' => $state['product_limit'] ?? 10,
                'plan_type' => $state['plan_id'] ?? TenantPlan::SEMILLA->value,
                'subscription_status' => $state['subscription_status'] ?? Tenant::DEFAULT_SUBSCRIPTION_STATUS,
                'subscription_ends_at' => $state['subscription_ends_at'] ?? null,
                'subscription_reference' => $state['subscription_reference'] ?? null,
                'version' => max(1, $tenant->version()),
            ],
            ['id'],
            [
                'name',
                'slug',
                'user_id',
                'is_active',
                'category_id',
                'city_id',
                'logo_path',
                'phone',
                'address',
                'latitude',
                'longitude',
                'description',
                'slogan',
                'whatsapp_number',
                'is_ai_enhanced',
                'is_pro',
                'product_limit',
                'plan_type',
                'subscription_status',
                'subscription_ends_at',
                'subscription_reference',
                'updated_at',
                'version',
            ]
        );
    }
}
