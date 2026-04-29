<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Settings\GeneralSettings;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Routing\Router;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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

        $settings = $this->generalSettings();

        return Inertia::render('Welcome', array_merge($data, [
            'canLogin' => $this->router->has('login'),
            'canRegister' => $this->router->has('tenant.register'),
            'settings' => [
                'freeProductLimit' => (int) ($settings?->free_plan_product_limit ?? config('conecta.free_plan_product_limit', 10)),
                'proProductLimit' => (int) ($settings?->pro_plan_product_limit ?? config('conecta.pro_plan_product_limit', 100)),
                'freePlanPrice' => (float) ($settings?->free_plan_price ?? 0.00),
                'proPlanPrice' => (float) ($settings?->pro_plan_price ?? config('conecta.pro_plan_price', 499.00)),
                'proPlanOriginalPrice' => (float) ($settings?->pro_plan_original_price ?? 799.00),
                'setupFeePrice' => (float) ($settings?->setup_fee_price ?? 2500.00),
            ],
        ]));
    }

    private function generalSettings(): ?GeneralSettings
    {
        try {
            return app(GeneralSettings::class);
        } catch (Throwable) {
            return null;
        }
    }
}
