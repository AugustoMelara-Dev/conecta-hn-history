<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class TenantMicrositeTrackingMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tenant_microsite_routes_include_tracking_middlewares(): void
    {
        [, $tenant] = $this->makeTenantOwner('tracking-microsite');

        $route = Route::getRoutes()->match(
            Request::create("/t/{$tenant->slug}", 'GET')
        );

        $middlewares = $route->gatherMiddleware();

        $this->assertContains('track.tenant', $middlewares);
        $this->assertContains('track.tenant.visit', $middlewares);
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(string $slug): array
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $city = City::factory()->create();
        $category = Category::factory()->create();

        $tenant = Tenant::factory()->state([
            'user_id' => $user->id,
            'slug' => $slug,
            'business_type' => 'retail',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ])->create();

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user, $tenant];
    }
}
