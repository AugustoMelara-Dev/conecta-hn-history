<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Jobs;

use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Jobs\Analytics\SyncTenantAnalytics;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use App\Models\TenantAnalytic;
use App\Models\TenantVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class SyncTenantAnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sync_tenant_analytics_uses_real_visit_signals_and_does_not_force_zeros(): void
    {
        [, $tenant] = $this->makeTenantOwner('sync-analytics-signals');
        $date = now()->toDateString();

        TenantVisit::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'session_hash' => 'hash-1',
            'user_agent' => 'Mozilla/5.0',
            'referer' => 'https://example.test',
            'device_type' => 'desktop',
        ]);

        TenantVisit::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'session_hash' => 'hash-2',
            'user_agent' => 'Mozilla/5.0',
            'referer' => 'https://example.test',
            'device_type' => 'desktop',
        ]);

        Cache::put("analytics:tenants:{$tenant->getKey()}:total:{$date}", 9, now()->addHours(1));

        (new SyncTenantAnalytics((string) $tenant->getKey(), $date))->handle();

        $analytic = TenantAnalytic::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->whereDate('date', $date)
            ->first();

        $this->assertInstanceOf(TenantAnalytic::class, $analytic);
        $this->assertSame(9, (int) $analytic->views);
        $this->assertSame(2, (int) $analytic->unique_visitors);
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
