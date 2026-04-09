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

    public function __invoke(): Response
    {
        try {
            $data = $this->cache->remember('v'.config('app.version').'_homepage_data', 3600, function (): array {
                return [
                    'categories' => $this->repository->getCategories(),
                    'cities' => $this->repository->getPopularCities(),
                    'newTenants' => $this->repository->getLatestTenants(),
                    'heroImage' => $this->repository->getRandomHeroImage(),
                    'stats' => $this->repository->getStats(),
                ];
            });
        } catch (\Throwable) {
            // Fallback for serverless/runtime scenarios where DB/cache backends are unavailable.
            $data = [
                'categories' => [],
                'cities' => [],
                'newTenants' => [],
                'heroImage' => null,
                'stats' => [
                    'tenants_count' => 0,
                    'cities_count' => 0,
                    'categories_count' => 0,
                    'products_count' => 0,
                ],
            ];
        }

        return Inertia::render('Welcome', array_merge($data, [
            'canLogin' => $this->router->has('login'),
            'canRegister' => $this->router->has('tenant.register'),
            'settings' => [
                'freeProductLimit' => (int) config('conecta.free_plan_product_limit', 10),
                'proProductLimit' => (int) config('conecta.pro_plan_product_limit', 100),
                'proPlanPrice' => (float) config('conecta.pro_plan_price', 500.00),
            ],
        ]));
    }
}
