<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class E2EOwnerFlowSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::query()->first();
        if (! $category) {
            $category = Category::query()->create([
                'name' => 'Servicios',
                'slug' => 'servicios',
                'icon' => 'store',
                'color' => '#334155',
            ]);
        }

        $city = City::query()->first();
        if (! $city) {
            $city = City::query()->create([
                'name' => 'Tegucigalpa',
                'slug' => 'tegucigalpa',
                'municipality_id' => null,
            ]);
        }

        $owner = User::query()->firstOrCreate(
            ['email' => 'owner-demo-ai-guide-hn@conectahn.test'],
            [
                'name' => 'Owner Demo AI Guide',
                'password' => Hash::make('ChangeMe123!'),
                'role' => UserRole::TENANT_OWNER->value,
                'email_verified_at' => now(),
            ]
        );

        $customer = User::query()->firstOrCreate(
            ['email' => 'customer-demo-ai-guide-hn@conectahn.test'],
            [
                'name' => 'Customer Demo AI Guide',
                'password' => Hash::make('ChangeMe123!'),
                'role' => UserRole::CUSTOMER->value,
                'email_verified_at' => now(),
            ]
        );

        $tenant = Tenant::query()->firstOrNew(['slug' => 'demo-ai-guide-hn']);
        if (! $tenant->exists) {
            $tenant->forceFill(['id' => '019d0000-0000-7000-8000-000000000003']);
        }

        $tenant->fill([
            'name' => 'Demo AI Guide HN',
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'city_id' => $city->id,
            'description' => 'Tenant semilla para smoke e2e de dueña de negocio.',
            'plan_type' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'business_type' => 'retail',
            'email' => $owner->email,
            'phone' => '+50499997777',
            'website_url' => 'https://demo-ai-guide-hn.local',
        ]);
        $tenant->save();

        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $customer->forceFill(['tenant_id' => null])->save();

        DB::table('personal_access_tokens')->updateOrInsert(['id' => 900001], [
            'tokenable_type' => User::class,
            'tokenable_id' => $owner->getKey(),
            'name' => 'critical-e2e-owner',
            'token' => hash('sha256', 'e2e-owner-economic-token'),
            'abilities' => '["tenant:019d0000-0000-7000-8000-000000000003"]',
            'last_used_at' => null,
            'expires_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // INFRA_TENANT_FILTER: deterministic E2E fixture lookup is constrained to the seeded tenant and exact product name.
        if (! Product::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Producto E2E confirmado')
            ->exists()) {
            Product::withoutEvents(static fn (): Product => Product::factory()
                ->forTenant($tenant)
                ->withStock(100)
                ->create([
                    'name' => 'Producto E2E confirmado',
                    'price' => 125.50,
                    'is_active' => true,
                    'is_visible' => true,
                ]));
        }
    }
}
