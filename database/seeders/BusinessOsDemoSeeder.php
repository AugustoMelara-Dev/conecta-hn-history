<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Category;
use App\Models\City;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class BusinessOsDemoSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::withoutEvents(function (): void {
            $this->superAdmin();

            foreach ($this->tenants() as $tenantData) {
                $category = $this->category($tenantData['category']);
                $city = $this->city($tenantData['city'], $tenantData['department']);
                $owner = $this->owner($tenantData['owner_email'], $tenantData['owner_name']);

                $tenant = Tenant::query()->withTrashed()->firstOrNew(['slug' => $tenantData['slug']]);

                $tenant->forceFill($this->onlyExistingColumns('tenants', [
                    'name' => $tenantData['name'],
                    'description' => $tenantData['description'],
                    'user_id' => $owner->id,
                    'category_id' => $category->id,
                    'city_id' => $city->id,
                    'status' => 'approved',
                    'is_active' => true,
                    'plan_type' => $tenantData['plan_type'],
                    'subscription_status' => 'active',
                    'is_pro' => $tenantData['plan_type'] !== 'semilla',
                    'product_limit' => $tenantData['plan_type'] === 'semilla' ? 8 : 80,
                    'business_type' => $tenantData['business_type'],
                    'commercial_mode' => $tenantData['commercial_mode'],
                    'rubro_key' => $tenantData['rubro_key'],
                    'tags' => $tenantData['tags'],
                    'whatsapp_number' => $tenantData['whatsapp'],
                    'phone' => $tenantData['phone'],
                    'address' => $tenantData['address'],
                    'primary_color' => '#0ea5e9',
                    'brand_color' => '#0ea5e9',
                    'seo_title' => $tenantData['name'].' en '.$city->name,
                    'seo_description' => $tenantData['description'],
                ]))->save();

                $owner->forceFill(['tenant_id' => $tenant->id])->save();
                $this->products($tenant, $tenantData['products']);
            }
        });
    }

    private function superAdmin(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@conectahn.com'],
            [
                'name' => 'Augusto Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::SUPER_ADMIN,
                'email_verified_at' => now(),
            ]
        );
    }

    private function category(array $data): Category
    {
        return Category::query()->firstOrCreate(
            ['slug' => $data['slug']],
            ['name' => $data['name'], 'icon' => 'store', 'color' => '#0ea5e9']
        );
    }

    private function city(string $name, string $departmentName): City
    {
        $department = Department::query()->firstOrCreate(
            ['slug' => Str::slug($departmentName)],
            ['name' => $departmentName]
        );

        $municipality = Municipality::query()->firstOrCreate(
            ['slug' => Str::slug($name).'-demo'],
            ['name' => $name, 'department_id' => $department->id]
        );

        return City::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'municipality_id' => $municipality->id]
        );
    }

    private function owner(string $email, string $name): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'role' => UserRole::TENANT_OWNER,
                'email_verified_at' => now(),
            ]
        );
    }

    private function products(Tenant $tenant, array $products): void
    {
        foreach ($products as $item) {
            // JOB_GLOBAL_POLL: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            Product::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => Str::slug($item['name'])],
                $this->onlyExistingColumns('products', [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'is_visible' => true,
                    'manage_stock' => true,
                    'stock_quantity' => 24,
                    'low_stock_threshold' => 4,
                    'stock_status' => \App\Enums\StockStatus::InStock,
                ])
            );
        }
    }

    private function onlyExistingColumns(string $table, array $attributes): array
    {
        return array_filter(
            $attributes,
            static fn (string $column): bool => Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function tenants(): array
    {
        return [
            [
                'name' => 'Mercado Norte',
                'slug' => 'mercado-norte',
                'description' => 'Abarroteria y productos para compras rapidas en San Pedro Sula.',
                'commercial_mode' => 'products',
                'business_type' => 'retail',
                'rubro_key' => 'retail-core',
                'plan_type' => 'semilla',
                'category' => ['name' => 'Tiendas', 'slug' => 'tiendas'],
                'city' => 'San Pedro Sula',
                'department' => 'Cortes',
                'owner_email' => 'owner.mercado.norte@example.test',
                'owner_name' => 'Laura Mejia',
                'whatsapp' => '50498765431',
                'phone' => '2550-1122',
                'address' => 'Barrio Rio de Piedras',
                'tags' => ['abarrotes', 'entrega', 'compras'],
                'products' => [
                    ['name' => 'Canasta basica pequena', 'description' => 'Arroz, frijoles, aceite y cafe para la semana.', 'price' => 150],
                    ['name' => 'Cafe molido local', 'description' => 'Cafe hondureno en bolsa de una libra.', 'price' => 118],
                ],
            ],
            [
                'name' => 'Clinica Vida Clara',
                'slug' => 'clinica-vida-clara',
                'description' => 'Consultas generales, controles preventivos y agenda por cita.',
                'commercial_mode' => 'appointments',
                'business_type' => 'service',
                'rubro_key' => 'service-core',
                'plan_type' => 'pro',
                'category' => ['name' => 'Salud', 'slug' => 'salud'],
                'city' => 'Tegucigalpa',
                'department' => 'Francisco Morazan',
                'owner_email' => 'owner.vida.clara@example.test',
                'owner_name' => 'Dra. Sofia Pineda',
                'whatsapp' => '50497654322',
                'phone' => '2221-8080',
                'address' => 'Colonia Palmira',
                'tags' => ['citas', 'salud', 'familia'],
                'products' => [
                    ['name' => 'Consulta general', 'description' => 'Evaluacion medica inicial con orientacion preventiva.', 'price' => 450],
                    ['name' => 'Control pediatrico', 'description' => 'Revision infantil con agenda por disponibilidad.', 'price' => 520],
                ],
            ],
            [
                'name' => 'Lexa Consultores',
                'slug' => 'lexa-consultores',
                'description' => 'Asesoria legal y empresarial para negocios que necesitan cotizar antes de contratar.',
                'commercial_mode' => 'consulting_leads',
                'business_type' => 'service',
                'rubro_key' => 'consulting-core',
                'plan_type' => 'pro',
                'category' => ['name' => 'Consultorias', 'slug' => 'consultorias'],
                'city' => 'La Ceiba',
                'department' => 'Atlantida',
                'owner_email' => 'owner.lexa@example.test',
                'owner_name' => 'Marco Zelaya',
                'whatsapp' => '50496543210',
                'phone' => '2440-3030',
                'address' => 'Avenida San Isidro',
                'tags' => ['cotizaciones', 'legal', 'empresas'],
                'products' => [
                    ['name' => 'Revision de contrato', 'description' => 'Analisis inicial y propuesta de siguientes pasos.', 'price' => 900],
                    ['name' => 'Constitucion de empresa', 'description' => 'Diagnostico para iniciar tramite societario.', 'price' => 1500],
                ],
            ],
        ];
    }
}
