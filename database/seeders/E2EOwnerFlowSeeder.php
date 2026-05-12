<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            ['email' => 'owner-demo-ai-guide-hn@vitobusinessos.app'],
            [
                'name' => 'Owner Demo AI Guide',
                'password' => Hash::make('ChangeMe123!'),
                'role' => UserRole::TENANT_OWNER->value,
                'email_verified_at' => now(),
            ]
        );

        $customer = User::query()->firstOrCreate(
            ['email' => 'customer-demo-ai-guide-hn@vitobusinessos.app'],
            [
                'name' => 'Customer Demo AI Guide',
                'password' => Hash::make('ChangeMe123!'),
                'role' => UserRole::USER->value,
                'email_verified_at' => now(),
            ]
        );

        $tenant = Tenant::query()->firstOrNew(['slug' => 'demo-ai-guide-hn']);
        if (! $tenant->exists) {
            $tenant->id = (string) Str::uuid();
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
            'is_visible' => true,
            'business_type' => 'service',
            'email' => $owner->email,
            'phone' => '+50499997777',
            'website_url' => 'https://demo-ai-guide-hn.local',
        ]);
        $tenant->save();

        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $customer->forceFill(['tenant_id' => null])->save();
    }
}
