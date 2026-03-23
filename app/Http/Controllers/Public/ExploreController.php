<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Shared\Contracts\ReferenceDataReaderInterface;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ExploreController - Marketplace Discovery Page
 *
 * LUXURY ENTRANCE STRATEGY:
 * - Root (/) = B2B Sales Landing (WelcomeController)
 * - /explore = Marketplace Discovery (this controller)
 * - /@{slug} = Tenant Microsites (TenantMicrositeController)
 *
 * This controller powers the discovery engine where users browse and find businesses.
 */
final class ExploreController extends Controller
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private ReferenceDataReaderInterface $referenceReader,
        private CacheRepository $cache
    ) {}

    /**
     * Display the marketplace discovery page.
     */
    public function __invoke(): Response
    {
        $data = $this->cache->remember('v'.config('app.version').'_explore_data', 3600, function (): array {
            return [
                'featuredTenants' => $this->tenantRepository->getFeatured(12),
                'categories' => $this->referenceReader->getPopularCategories(8),
                'cities' => $this->referenceReader->getPopularCities(4),
                'stats' => [
                    'total_tenants' => $this->tenantRepository->countActive(),
                    'total_cities' => $this->referenceReader->countActiveCities(),
                ],
            ];
        });

        return Inertia::render('Public/Explore', $data);
    }
}
