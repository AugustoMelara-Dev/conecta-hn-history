<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Routing\Router;
use Inertia\Inertia;
use Inertia\Response;

final class WelcomeController extends Controller
{
    public function __construct(
        private \App\Domain\Public\Contracts\LandingPageRepository $repository,
        private CacheRepository $cache,
        private Router $router
    ) {}

    /**
     * Display the landing page.
     *
     * Data parity with Livewire Home.php:
     * - categories: Active categories for navigation pills
     * - cities: Popular cities with tenant counts
     * - newTenants: Latest 8 businesses for social proof
     * - heroImage: Random city hero image for background
     * - stats: Aggregate counts for trust badges
     */
    public function __invoke(): Response
    {
        $data = $this->cache->remember('v'.config('app.version').'_homepage_data', 3600, function () {
            return [
                // ═══════════════════════════════════════════════════════════
                // CATEGORIES: For navigation pills (sorted by sort_order)
                // ═══════════════════════════════════════════════════════════
                'categories' => $this->repository->getCategories(),

                // ═══════════════════════════════════════════════════════════
                // CITIES: Popular cities with tenant counts
                // ═══════════════════════════════════════════════════════════
                'cities' => $this->repository->getPopularCities(),

                // ═══════════════════════════════════════════════════════════
                // NEW TENANTS: Latest businesses for social proof
                // ═══════════════════════════════════════════════════════════
                'newTenants' => $this->repository->getLatestTenants(),

                // ═══════════════════════════════════════════════════════════
                // HERO IMAGE: Random city background
                // ═══════════════════════════════════════════════════════════
                'heroImage' => $this->repository->getRandomHeroImage(),

                // ═══════════════════════════════════════════════════════════
                // STATS: Aggregate counts for trust badges
                // ═══════════════════════════════════════════════════════════
                'stats' => $this->repository->getStats(),
            ];
        });

        return Inertia::render('Welcome', array_merge($data, [
            'canLogin' => $this->router->has('login'),
            'canRegister' => $this->router->has('register'),
        ]));
    }
}
