<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Application\Shared\Contracts\ReferenceDataReaderInterface;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Spatie\OpeningHours\OpeningHours;
use Tests\TestCase;

final class ExploreControllerFallbackTest extends TestCase
{
    #[Test]
    public function explore_keeps_working_when_backend_dependencies_fail(): void
    {
        $this->app->instance(TenantRepositoryInterface::class, new class implements TenantRepositoryInterface
        {
            public function findById(string $id): ?TenantEntity
            {
                return null;
            }

            public function findByIdForUpdate(string $id): ?TenantEntity
            {
                return null;
            }

            public function findBySlug(string $slug): ?TenantEntity
            {
                return null;
            }

            public function existsBySlug(string $slug): bool
            {
                return false;
            }

            public function findByDomain(string $domain): ?TenantEntity
            {
                return null;
            }

            public function getFeatured(int $limit = 12): array
            {
                throw new \RuntimeException('Simulated backend failure');
            }

            public function countActive(): int
            {
                return 0;
            }

            public function getOpeningHours(string $tenantId): OpeningHours
            {
                return OpeningHours::create([]);
            }

            public function create(array $attributes): TenantEntity
            {
                throw new \RuntimeException('Not implemented for this test');
            }

            public function save(TenantEntity $tenant): TenantEntity
            {
                return $tenant;
            }

            public function updateOptimistically(TenantEntity $tenant): void {}

            public function delete(TenantEntity $tenant): void {}
        });

        $this->app->instance(ReferenceDataReaderInterface::class, new class implements ReferenceDataReaderInterface
        {
            public function getAvailableCities(): array
            {
                return [];
            }

            public function getActiveCategories(): array
            {
                return [];
            }

            public function getPopularCategories(int $limit = 8): array
            {
                return [];
            }

            public function getPopularCities(int $limit = 4): array
            {
                return [];
            }

            public function countActiveCities(): int
            {
                return 0;
            }
        });

        $response = $this->get('/explore');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Public/Explore')
            ->where('featuredTenants', [])
            ->where('categories', [])
            ->where('cities', [])
            ->where('stats.total_tenants', 0)
            ->where('stats.total_cities', 0)
        );
    }
}
