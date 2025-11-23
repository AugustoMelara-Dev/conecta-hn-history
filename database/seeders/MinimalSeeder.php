<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MinimalSeeder extends Seeder
{
    public function run()
    {
        // Admin
        DB::table('users')->insert([
            'name' => 'Super Admin',
            'email' => 'admin@conectahn.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seller
        DB::table('users')->insert([
            'name' => 'Carlos Martinez',
            'email' => 'seller@test.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Categories
        DB::table('categories')->insert([
            ['name' => 'Restaurantes', 'slug' => 'restaurantes', 'icon' => 'utensils', 'color' => '#ef4444', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Tecnología', 'slug' => 'tecnologia', 'icon' => 'laptop', 'color' => '#3b82f6', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Salud', 'slug' => 'salud', 'icon' => 'heart', 'color' => '#10b981', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 8 Businesses
        $businesses = [
            ['name' => 'Café El Indio', 'category_id' => 1, 'city' => 'Tegucigalpa', 'is_pro' => 1],
            ['name' => 'TechStore SPS', 'category_id' => 2, 'city' => 'San Pedro Sula', 'is_pro' => 1],
            ['name' => 'Farmacia Salud', 'category_id' => 3, 'city' => 'La Ceiba', 'is_pro' => 1],
            ['name' => 'Restaurante Don Pepe', 'category_id' => 1, 'city' => 'Tegucigalpa', 'is_pro' => 1],
            ['name' => 'Inversiones La Paz', 'category_id' => 2, 'city' => 'San Pedro Sula', 'is_pro' => 1],
            ['name' => 'Comedor Suyapa', 'category_id' => 1, 'city' => 'Tegucigalpa', 'is_pro' => 0],
            ['name' => 'Tienda La Esquina', 'category_id' => 2, 'city' => 'La Ceiba', 'is_pro' => 0],
            ['name' => 'Barbería El Corte', 'category_id' => 3, 'city' => 'San Pedro Sula', 'is_pro' => 0],
        ];

        foreach ($businesses as $idx => $biz) {
            DB::table('tenants')->insert([
                'user_id' => 2,
                'name' => $biz['name'],
                'slug' => str_replace(' ', '-', strtolower($biz['name'])),
                'category_id' => $biz['category_id'],
                'description' => 'Negocio profesional en ' . $biz['city'],
                'city' => $biz['city'],
                'status' => 'approved',
                'is_pro' => $biz['is_pro'],
                'logo_path' => 'https://images.unsplash.com/photo-150000000' . ($idx + 1) . '000?w=400&q=80',
                'primary_color' => ['#7c3aed', '#dc2626', '#059669'][($idx % 3)],
                'whatsapp_number' => '+504 9999-999' . $idx,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        echo "✅ Minimal seed complete: 2 users, 3 categories, 8 businesses\n";
    }
}
