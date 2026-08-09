<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\User;
use App\Domain\TenantGovernance\Models\Tenant;
use App\Models\Category;
use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MinimalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * USES DOMAIN FACTORIES FOR INTEGRITY.
     */
    public function run(): void
    {
        // 1. Create Super Admin
        // Using Identity Domain Factory
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@conectahn.com',
            'role' => 'super_admin',
            'password' => 'password', // Model has 'hashed' cast, will autopassword
        ]);

        // 2. Create a Seller User
        $seller = User::factory()->create([
            'name' => 'Carlos Martinez',
            'email' => 'seller@test.com',
            'role' => 'seller',
        ]);

        // 3. Create Categories (Infra/Core Models)
        $categories = [
            ['name' => 'Restaurantes', 'slug' => 'restaurantes', 'icon' => 'utensils', 'color' => '#ef4444'],
            ['name' => 'Tecnología', 'slug' => 'tecnologia', 'icon' => 'laptop', 'color' => '#3b82f6'],
            ['name' => 'Salud', 'slug' => 'salud', 'icon' => 'heart', 'color' => '#10b981'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        // 4. Create Tenants for the Seller
        // Using TenantGovernance Domain Factory
        $businesses = [
            ['name' => 'Café El Indio', 'city' => 'Tegucigalpa', 'is_pro' => true],
            ['name' => 'TechStore SPS', 'city' => 'San Pedro Sula', 'is_pro' => true],
            ['name' => 'Farmacia Salud', 'city' => 'La Ceiba', 'is_pro' => true],
            ['name' => 'Restaurante Don Pepe', 'city' => 'Tegucigalpa', 'is_pro' => true],
            ['name' => 'Inversiones La Paz', 'city' => 'San Pedro Sula', 'is_pro' => true],
            ['name' => 'Comedor Suyapa', 'city' => 'Tegucigalpa', 'is_pro' => false],
            ['name' => 'Tienda La Esquina', 'city' => 'La Ceiba', 'is_pro' => false],
            ['name' => 'Barbería El Corte', 'city' => 'San Pedro Sula', 'is_pro' => false],
        ];

        // Retrieve category IDs
        $restaurantes = Category::where('slug', 'restaurantes')->first()->id;
        $tecnologia = Category::where('slug', 'tecnologia')->first()->id;
        $salud = Category::where('slug', 'salud')->first()->id;

        // Retrieve Cities (Mock or create if not exist for minimal seed)
        // In a real scenario, use CitySeeder. Here ensuring they exist.
        $cities = ['Tegucigalpa', 'San Pedro Sula', 'La Ceiba'];
        foreach ($cities as $cityName) {
            City::firstOrCreate(
                ['slug' => Str::slug($cityName)],
                ['name' => $cityName, 'department_id' => 1] // Assuming dept 1 exists or is optional
            );
        }

        foreach ($businesses as $idx => $biz) {
            $categoryId = match ($idx % 3) {
                0 => $restaurantes,
                1 => $tecnologia,
                2 => $salud,
            };

            $cityId = City::where('name', $biz['city'])->first()->id;

            $factory = Tenant::factory()
                ->state([
                    'user_id' => $seller->id,
                    'name' => $biz['name'],
                    'slug' => Str::slug($biz['name']),
                    'category_id' => $categoryId,
                    'city_id' => $cityId,
                    'description' => 'Negocio profesional en '.$biz['city'],
                    'is_active' => true,
                    'primary_color' => ['#7c3aed', '#dc2626', '#059669'][$idx % 3],
                    'whatsapp_number' => '+504 9999-999'.$idx,
                ]);

            if ($biz['is_pro']) {
                $factory = $factory->pro();
            } else {
                $factory = $factory->state(['plan_type' => 'semilla']);
            }

            $factory->create();
        }

        $this->command->info('✅ Minimal seed complete: Domain Factories utilized.');
    }
}
