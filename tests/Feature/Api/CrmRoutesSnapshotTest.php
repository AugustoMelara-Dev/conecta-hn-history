<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\V1\Crm\CrmDealController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CrmRoutesSnapshotTest extends TestCase
{
    public function test_crm_route_surface_remains_stable(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/crm'))
            ->map(fn ($route): ?string => $route->getName())
            ->filter()
            ->values()
            ->all();

        foreach ([
            'api.crm.pipelines.index',
            'api.crm.pipeline-summary.show',
            'api.crm.deals.index',
            'api.crm.deals.store',
            'api.crm.deals.import',
            'api.crm.deals.export',
            'api.crm.leads.convert-to-deal',
            'api.crm.pipelines.store',
            'api.crm.pipelines.update',
            'api.crm.pipelines.destroy',
            'api.crm.pipelines.stages.store',
            'api.crm.stages.update',
            'api.crm.stages.destroy',
            'api.crm.deals.update',
            'api.crm.deals.stage.update',
            'api.crm.deals.destroy',
        ] as $expectedName) {
            $this->assertContains($expectedName, $names);
        }
    }

    public function test_canonical_controller_owns_every_mutating_deal_route(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/crm/deals'))
            ->filter(fn ($route): bool => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
            ->values();

        self::assertNotEmpty($routes);

        foreach ($routes as $route) {
            self::assertStringStartsWith(
                CrmDealController::class.'@',
                $route->getActionName(),
                "Non-canonical CRM mutation route: {$route->uri()}",
            );
        }
    }
}
