<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Category;
use App\Models\City;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Product;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * IMPORTANT: Observers are silenced during seeding to prevent
     * cascading side-effects (ActivityLog UUID/bigint mismatch, etc.).
     */
    public function run(): void
    {
        // Silence observers to prevent cascading side-effects during seeding
        Tenant::withoutEvents(function () {
            User::withoutEvents(function () {
                $this->seedData();
            });
        });
    }

    private function seedData(): void
    {
        // 0. Limpieza (Opción mental - en realidad solo creamos si no existe)

        // 1. Crear Categorías
        $categoriesData = [
            ['name' => 'Automotriz', 'slug' => 'automotriz', 'icon' => '🚗', 'color' => '#ef4444'],
            ['name' => 'Restaurantes', 'slug' => 'restaurantes', 'icon' => '🍔', 'color' => '#f97316'],
            ['name' => 'Tecnología', 'slug' => 'tecnologia', 'icon' => '💻', 'color' => '#3b82f6'],
            ['name' => 'Salud', 'slug' => 'salud', 'icon' => '🩺', 'color' => '#10b981'],
            ['name' => 'Hogar', 'slug' => 'hogar', 'icon' => '🏠', 'color' => '#8b5cf6'],
            ['name' => 'Moda', 'slug' => 'moda', 'icon' => '👗', 'color' => '#ec4899'],
            ['name' => 'Servicios', 'slug' => 'servicios', 'icon' => '🛠️', 'color' => '#6b7280'],
            ['name' => 'Artesanía', 'slug' => 'artesania', 'icon' => '🏺', 'color' => '#92400e'],
        ];

        $categories = collect();
        foreach ($categoriesData as $cat) {
            $categories->push(Category::firstOrCreate(['slug' => $cat['slug']], $cat));
        }

        // 2. Crear Jerarquía Geográfica
        $cortes = Department::firstOrCreate(['name' => 'Cortés', 'slug' => 'cortes']);
        $spsMuni = Municipality::firstOrCreate(['name' => 'San Pedro Sula', 'slug' => 'sps-muni', 'department_id' => $cortes->id]);
        $sps = City::firstOrCreate(['name' => 'San Pedro Sula', 'slug' => 'san-pedro-sula', 'municipality_id' => $spsMuni->id], ['coordinates' => ['lat' => 15.5042, 'lng' => -88.0250]]);

        $fm = Department::firstOrCreate(['name' => 'Francisco Morazán', 'slug' => 'francisco-morazan']);
        $dc = Municipality::firstOrCreate(['name' => 'Distrito Central', 'slug' => 'distrito-central', 'department_id' => $fm->id]);
        $tegus = City::firstOrCreate(['name' => 'Tegucigalpa', 'slug' => 'tegucigalpa', 'municipality_id' => $dc->id], ['coordinates' => ['lat' => 14.0723, 'lng' => -87.1921]]);

        $atlantida = Department::firstOrCreate(['name' => 'Atlántida', 'slug' => 'atlantida']);
        $ceibaMuni = Municipality::firstOrCreate(['name' => 'La Ceiba', 'slug' => 'la-ceiba-muni', 'department_id' => $atlantida->id]);
        $ceiba = City::firstOrCreate(['name' => 'La Ceiba', 'slug' => 'la-ceiba', 'municipality_id' => $ceibaMuni->id]);

        $colon = Department::firstOrCreate(['name' => 'Colón', 'slug' => 'colon']);
        $tocoaMuni = Municipality::firstOrCreate(['name' => 'Tocoa', 'slug' => 'tocoa-muni', 'department_id' => $colon->id]);
        $tocoa = City::firstOrCreate(['name' => 'Tocoa', 'slug' => 'tocoa', 'municipality_id' => $tocoaMuni->id]);

        $cities = [$sps, $tegus, $ceiba, $tocoa];

        // 3. Usuarios Clave
        // Super Admin
        User::firstOrCreate(
            ['email' => 'admin@conectahn.com'],
            [
                'name' => 'Augusto Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::SUPER_ADMIN,
                'email_verified_at' => now(),
            ]
        );

        // Vendedor Genérico — will own first PRO tenant (Sushi Master)
        $seller = User::firstOrCreate(
            ['email' => 'vendedor@test.com'],
            [
                'name' => 'Juan Vendedor',
                'password' => Hash::make('password'),
                'role' => UserRole::TENANT_OWNER,
                'email_verified_at' => now(),
            ]
        );

        // 4. Generación de Tenants (Ecosistema Vivo)

        // Helper para crear productos
        $createProducts = function ($tenant, $count) {
            Product::factory()->count($count)->create([
                'tenant_id' => $tenant->id,
                'is_visible' => true,
            ]);
        };

        // --- A. Negocios PRO (5) ---
        $proTenantsData = [
            ['name' => 'Sushi Master', 'cat' => 'restaurantes', 'city' => $tegus],
            ['name' => 'TechZone Honduras', 'cat' => 'tecnologia', 'city' => $sps],
            ['name' => 'Moda Catracha', 'cat' => 'moda', 'city' => $ceiba],
            ['name' => 'Clínica Bienestar', 'cat' => 'salud', 'city' => $tegus],
            ['name' => 'Muebles Rústicos', 'cat' => 'hogar', 'city' => $tocoa],
        ];

        foreach ($proTenantsData as $idx => $data) {
            // First PRO tenant (Sushi Master) is owned by vendedor@test.com
            $user = ($idx === 0)
                ? $seller
                : User::factory()->create(['role' => UserRole::TENANT_OWNER, 'name' => 'Dueño '.$data['name']]);

            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => \Illuminate\Support\Str::slug($data['name']),
                'user_id' => $user->id,
                'category_id' => $categories->where('slug', $data['cat'])->first()->id,
                'city_id' => $data['city']->id,
                'status' => 'approved',
                'is_active' => true,
                'plan_type' => 'pro',
                'subscription_status' => 'active',
                'product_limit' => 999,
                'banner_path' => null, // Dejamos null o pondríamos path real si tuviéramos archivos
                'description' => "Somos líderes en {$data['name']}. Ofrecemos la mejor calidad y servicio en ".$data['city']->name.'.',
                'social_links' => json_encode([
                    ['platform' => 'facebook', 'url' => 'https://facebook.com'],
                    ['platform' => 'instagram', 'url' => 'https://instagram.com'],
                ]),
                'whatsapp_number' => '50499999999',
                'primary_color' => $categoriesData[$idx % count($categoriesData)]['color'], // Color temático
            ]);

            // Assign tenant_id to owner so they can access dashboard
            $user->tenant_id = $tenant->id;
            $user->save();

            $createProducts($tenant, rand(10, 20));
            // Reviews commented out due to factory dependency issues
            // Review::factory()->count(rand(3, 8))->create(['tenant_id' => $tenant->id]);
        }

        // --- B. Negocios GRATUITOS (5) ---
        $freeTenantsData = [
            ['name' => 'Antojitos Tía Paquita', 'cat' => 'restaurantes', 'city' => $tocoa],
            ['name' => 'Reparaciones Rápidas', 'cat' => 'servicios', 'city' => $sps],
            ['name' => 'Bazar El Centro', 'cat' => 'hogar', 'city' => $ceiba],
            ['name' => 'Comedor Los Primos', 'cat' => 'restaurantes', 'city' => $tegus],
            ['name' => 'Artesanías Lencas', 'cat' => 'artesania', 'city' => $tegus],
        ];

        foreach ($freeTenantsData as $data) {
            $user = User::factory()->create(['role' => UserRole::TENANT_OWNER, 'name' => 'Dueño '.$data['name']]);

            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => \Illuminate\Support\Str::slug($data['name']),
                'user_id' => $user->id,
                'category_id' => $categories->where('slug', $data['cat'])->first()->id,
                'city_id' => $data['city']->id,
                'status' => 'approved',
                'is_active' => true,
                'plan_type' => 'semilla',
                'subscription_status' => 'active',
                'product_limit' => 10,
                'banner_path' => null,
                'description' => "Negocio local de confianza en {$data['city']->name}.",
                'social_links' => null, // Sin redes para free
                'whatsapp_number' => '50488888888',
            ]);

            // Assign tenant_id to owner so they can access dashboard
            $user->tenant_id = $tenant->id;
            $user->save();

            $createProducts($tenant, rand(3, 8)); // Menos productos
        }

        // --- C. Negocios EN REVISIÓN (2) ---
        // Estos NO deben aparecer en el directorio público
        $pendingTenants = [
            ['name' => 'Droguería Falsa', 'cat' => 'salud'],
            ['name' => 'Tienda de Pruebas Beta', 'cat' => 'tecnologia'],
        ];

        foreach ($pendingTenants as $data) {
            $user = User::factory()->create(['role' => UserRole::TENANT_OWNER]);

            Tenant::create([
                'name' => $data['name'],
                'slug' => \Illuminate\Support\Str::slug($data['name']),
                'user_id' => $user->id,
                'category_id' => $categories->where('slug', $data['cat'])->first()->id,
                'city_id' => $tegus->id,
                'status' => 'pending', // CRÍTICO: Pendiente de aprobación
                'is_active' => 0,
                'plan_type' => 'semilla',
                'subscription_status' => 'pending',
                'description' => 'Este negocio está esperando aprobación del admin.',
            ]);
        }
    }
}
