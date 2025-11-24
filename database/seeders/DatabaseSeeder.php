<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\City;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear Categorías
        $categories = [
            ['name' => 'Automotriz', 'slug' => 'automotriz', 'icon' => 'car'],
            ['name' => 'Restaurantes', 'slug' => 'restaurantes', 'icon' => 'utensils'],
            ['name' => 'Tecnología', 'slug' => 'tecnologia', 'icon' => 'laptop'],
            ['name' => 'Salud', 'slug' => 'salud', 'icon' => 'activity'],
            ['name' => 'Hogar', 'slug' => 'hogar', 'icon' => 'home'],
            ['name' => 'Moda', 'slug' => 'moda', 'icon' => 'shirt'],
            ['name' => 'Servicios', 'slug' => 'servicios', 'icon' => 'briefcase'],
            ['name' => 'Supermercado', 'slug' => 'supermercado', 'icon' => 'shopping-cart'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        // 2. Crear Jerarquía Geográfica (Departamento -> Municipio -> Ciudad)

        // Zona Norte
        $cortes = Department::firstOrCreate(['name' => 'Cortés', 'slug' => 'cortes']);
        $spsMuni = Municipality::firstOrCreate(['name' => 'San Pedro Sula', 'slug' => 'sps-muni', 'department_id' => $cortes->id]);
        $sps = City::firstOrCreate(['name' => 'San Pedro Sula', 'slug' => 'san-pedro-sula', 'municipality_id' => $spsMuni->id], ['coordinates' => ['lat' => 15.5042, 'lng' => -88.0250]]);

        $cholomaMuni = Municipality::firstOrCreate(['name' => 'Choloma', 'slug' => 'choloma-muni', 'department_id' => $cortes->id]);
        $choloma = City::firstOrCreate(['name' => 'Choloma', 'slug' => 'choloma', 'municipality_id' => $cholomaMuni->id]);

        // Zona Centro
        $fm = Department::firstOrCreate(['name' => 'Francisco Morazán', 'slug' => 'francisco-morazan']);
        $dc = Municipality::firstOrCreate(['name' => 'Distrito Central', 'slug' => 'distrito-central', 'department_id' => $fm->id]);
        $tegus = City::firstOrCreate(['name' => 'Tegucigalpa', 'slug' => 'tegucigalpa', 'municipality_id' => $dc->id], ['coordinates' => ['lat' => 14.0723, 'lng' => -87.1921]]);

        // Zona Atlántica
        $atlantida = Department::firstOrCreate(['name' => 'Atlántida', 'slug' => 'atlantida']);
        $ceibaMuni = Municipality::firstOrCreate(['name' => 'La Ceiba', 'slug' => 'la-ceiba-muni', 'department_id' => $atlantida->id]);
        $ceiba = City::firstOrCreate(['name' => 'La Ceiba', 'slug' => 'la-ceiba', 'municipality_id' => $ceibaMuni->id]);

        $colon = Department::firstOrCreate(['name' => 'Colón', 'slug' => 'colon']);
        $tocoaMuni = Municipality::firstOrCreate(['name' => 'Tocoa', 'slug' => 'tocoa-muni', 'department_id' => $colon->id]);
        $tocoa = City::firstOrCreate(['name' => 'Tocoa', 'slug' => 'tocoa', 'municipality_id' => $tocoaMuni->id]);

        // 3. Crear Usuario Admin
        User::firstOrCreate(
            ['email' => 'admin@conectahn.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        // 4. Crear Vendedor de Prueba
        $seller = User::firstOrCreate(
            ['email' => 'vendedor@ejemplo.com'],
            [
                'name' => 'Juan Vendedor',
                'password' => Hash::make('password'),
                'role' => 'seller',
            ]
        );

        // 5. Crear Tenant de Prueba
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'autopartes-honduras'],
            [
                'name' => 'AutoPartes Honduras',
                'user_id' => $seller->id,
                'city_id' => $tegus->id,
                'category_id' => Category::where('slug', 'automotriz')->first()->id,
                'is_pro' => true,
                'description' => 'Repuestos originales para todas las marcas.',
                'status' => 'approved',
            ]
        );
    }
}
