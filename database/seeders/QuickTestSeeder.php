<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuickTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating Quick Test Data...');

        // 1. Create Categories
        $categories = [
            Category::create(['name' => 'Restaurantes', 'slug' => 'restaurantes', 'icon' => 'utensils', 'color' => '#ef4444']),
            Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia', 'icon' => 'laptop', 'color' => '#3b82f6']),
            Category::create(['name' => 'Salud', 'slug' => 'salud', 'icon' => 'heart', 'color' => '#10b981']),
        ];

        $this->command->info('✅ 3 Categories created');

        // 2. Create Test Users
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@conectahn.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $seller1 = User::create([
            'name' => 'Café El Indio',
            'email' => 'cafe@test.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
        ]);

        $seller2 = User::create([
            'name' => 'TechStore',
            'email' => 'tech@test.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
        ]);

        $this->command->info('✅ 3 Users created');

        // 3. Create Test Tenants
        $tenant1 = Tenant::create([
            'user_id' => $seller1->id,
            'name' => 'Café El Indio',
            'slug' => 'cafe-el-indio',
            'category_id' => $categories[0]->id,
            'description' => 'El mejor café hondureño con tradición desde 1950. Especialidad en baleadas y café gourmet.',
            'city' => 'Tegucigalpa',
            'status' => 'approved',
            'is_pro' => true,
            'logo_path' => 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=400&q=80',
            'primary_color' => '#7c3aed',
            'whatsapp_number' => '+504 9999-9999',
            'whatsapp_template' => 'Hola, me interesa conocer más sobre {product_name}',
            'address' => 'Col. Palmira, Tegucigalpa',
        ]);

        Tenant::create([
            'user_id' => $seller2->id,
            'name' => 'TechStore Honduras',
            'slug' => 'techstore-honduras',
            'category_id' => $categories[1]->id,
            'description' => 'La mejor tecnología al mejor precio. Laptops, celulares, accesorios y más.',
            'city' => 'San Pedro Sula',
            'status' => 'approved',
            'is_pro' => true,
            'logo_path' => 'https://images.unsplash.com/photo-1531297484001-80022131f5a1?w=400&q=80',
            'primary_color' => '#dc2626',
            'whatsapp_number' => '+504 8888-8888',
            'whatsapp_template' => '¡Hola! Necesito información sobre {product_name}',
            'address' => 'Blvd. del Norte, San Pedro Sula',
        ]);

        Tenant::create([
            'user_id' => $seller2->id,
            'name' => 'Farmacia Salud Total',
            'slug' => 'farmacia-salud-total',
            'category_id' => $categories[2]->id,
            'description' => 'Tu salud es nuestra prioridad. Medicamentos, vitaminas y atención farmacéutica profesional.',
            'city' => 'La Ceiba',
            'status' => 'approved',
            'is_pro' => false,
            'logo_path' => 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=400&q=80',
            'primary_color' => '#059669',
            'whatsapp_number' => '+504 7777-7777',
            'address' => 'Barrio La Isla, La Ceiba',
        ]);

        $this->command->info('✅ 3 Tenants created');
        $this->command->info('🎉 Quick test data ready!');
        $this->command->newLine();
        $this->command->info('Login credentials:');
        $this->command->info('  Admin: admin@conectahn.com / password');
        $this->command->info('  Café El Indio: cafe@test.com / password');
        $this->command->info('  TechStore: tech@test.com / password');
    }
}
