<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

final class WelcomeController extends Controller
{
    public function __construct(
        private readonly \App\Domain\Public\Contracts\LandingPageRepository $repository
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
        $data = Cache::remember('homepage_data_v1', 3600, function () {
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
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
        ]));
    }
}
