<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class TenantOwnershipMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    public function test_staff_membership_cannot_access_foreign_tenant_bound_model(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('ownership-a');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('ownership-b');

        $staff = User::factory()->create([
            'role' => UserRole::STAFF,
        ]);

        TenantMembership::query()->create([
            'tenant_id' => (string) $tenantA->getKey(),
            'user_id' => (string) $staff->getKey(),
            'role' => 'operator',
            'accepted_at' => now(),
        ]);

        $allowedOrder = Order::factory()
            ->forTenant($tenantA)
            ->create(['user_id' => $ownerA->getKey()]);

        $foreignOrder = Order::factory()
            ->forTenant($tenantB)
            ->create(['user_id' => $ownerB->getKey()]);

        $uri = '/_security/tenant-ownership/'.Str::uuid();
        $parameter = 'securityOrder';

        Route::bind($parameter, static fn (string $value): ?Order => Order::query()
            ->withoutGlobalScopes()
            ->find($value));

        Route::middleware(['web', 'auth', 'tenant.ownership'])->get($uri.'/{'.$parameter.'}', function (Order $securityOrder) {
            return response()->json(['id' => $securityOrder->getKey()]);
        });

        $this->actingAs($staff)->get($uri.'/'.$allowedOrder->getKey())->assertOk();
        $this->actingAs($staff)->get($uri.'/'.$foreignOrder->getKey())->assertNotFound();
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function makeTenantWithOwner(string $slugPrefix): array
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $city = City::factory()->create();
        $category = Category::factory()->create();

        $tenant = Tenant::factory()->state([
            'user_id' => $owner->id,
            'slug' => $slugPrefix.'-'.Str::lower((string) Str::uuid()),
            'business_type' => 'retail',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ])->create();

        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        return [$tenant, $owner];
    }
}
