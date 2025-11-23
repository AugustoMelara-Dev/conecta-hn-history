<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Starting Enterprise Data Seeding (HOTFIXED)...');
        $this->command->newLine();

        // ==========================================
        // 1. CREATE USERS
        // ==========================================
        $this->command->info('Creating Users...');
        
        // Super Admin
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@conectahn.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'email_verified_at' => now(),
        ]);

        // Sellers (30 distinct Honduran names)
        $sellerNames = [
            'Carlos Martínez', 'María López', 'José Hernández', 'Ana García',
            'Roberto Rodríguez', 'Patricia Fernández', 'Luis González', 'Carmen Pérez',
            'Francisco Sánchez', 'Isabel Ramírez', 'Miguel Torres', 'Rosa Flores',
            'Antonio Cruz', 'Teresa Gómez', 'Pedro Díaz', 'Laura Morales',
            'Juan Jiménez', 'Elena Vargas', 'Jorge Romero', 'Silvia Muñoz',
            'Alberto Ortiz', 'Marta Silva', 'Fernando Castro', 'Andrea Ruiz',
            'Ricardo Medina', 'Gabriela López', 'Rafael Núñez', 'Claudia Reyes',
            'Enrique Soto', 'Verónica Delgado'
        ];

        $sellers = [];
        foreach ($sellerNames as $name) {
            $sellers[] = User::create([
                'name' => $name,
                'email' => Str::slug($name) . '@negocios.hn',
                'password' => Hash::make('password'),
                'role' => 'seller',
                'email_verified_at' => now(),
            ]);
        }

        // Buyers (50)
        $buyers = User::factory(50)->create([
            'role' => 'buyer',
            'password' => Hash::make('password'),
        ]);

        $this->command->info("✅ Users created: 1 admin + 30 sellers + 50 buyers");

        // ==========================================
        // 2. CREATE CATEGORIES
        // ==========================================
        $this->command->info('Creating Categories...');
        
        $categoriesData = [
            ['name' => 'Restaurantes', 'slug' => 'restaurantes', 'icon' => 'utensils', 'color' => '#ef4444'],
            ['name' => 'Tecnología', 'slug' => 'tecnologia', 'icon' => 'laptop', 'color' => '#3b82f6'],
            ['name' => 'Salud', 'slug' => 'salud', 'icon' => 'heart', 'color' => '#10b981'],
            ['name' => 'Servicios', 'slug' => 'servicios', 'icon' => 'wrench', 'color' => '#8b5cf6'],
            ['name' => 'Moda', 'slug' => 'moda', 'icon' => 'shirt', 'color' => '#ec4899'],
            ['name' => 'Hogar', 'slug' => 'hogar', 'icon' => 'home', 'color' => '#f59e0b'],
            ['name' => 'Automotriz', 'slug' => 'automotriz', 'icon' => 'car', 'color' => '#6366f1'],
            ['name' => 'Supermercado', 'slug' => 'supermercado', 'icon' => 'shopping-basket', 'color' => '#06b6d4'],
        ];

        $categoryIds = [];
        foreach ($categoriesData as $catData) {
            $catData['created_at'] = now();
            $catData['updated_at'] = now();
            \DB::table('categories')->insert($catData);
            $categoryIds[] = \DB::getPdo()->lastInsertId();
        }

        $this->command->info('✅ 8 Categories created with Lucide icons');

        // ==========================================
        // 3. CREATE 40 TENANTS (BUSINESSES)
        // ==========================================
        $this->command->info('Creating 40 Honduran Businesses...');

        $cities = ['Tegucigalpa', 'San Pedro Sula', 'La Ceiba'];
        $brandColors = ['#000000', '#1e40af', '#7c3aed', '#dc2626', '#059669', '#ea580c', '#0891b2'];

        // 20 PRO Businesses
        $proBusinesses = [
            ['name' => 'Café El Indio', 'category' => 0, 'city' => 'Tegucigalpa'],
            ['name' => 'Inversiones La Paz', 'category' => 1, 'city' => 'Tegucigalpa'],
            ['name' => 'TechStore SPS', 'category' => 1, 'city' => 'San Pedro Sula'],
            ['name' => 'Farmacia del Pueblo', 'category' => 2, 'city' => 'Tegucigalpa'],
            ['name' => 'Restaurante Don Pepe', 'category' => 0, 'city' => 'San Pedro Sula'],
            ['name' => 'Taller Mecánico El Experto', 'category' => 6, 'city' => 'La Ceiba'],
            ['name' => 'Boutique Elegancia', 'category' => 4, 'city' => 'Tegucigalpa'],
            ['name' => 'Supermercado La Economía', 'category' => 7, 'city' => 'San Pedro Sula'],
            ['name' => 'Clínica Médica Integral', 'category' => 2, 'city' => 'Tegucigalpa'],
            ['name' => 'Ferretería La Construcción', 'category' => 5, 'city' => 'San Pedro Sula'],
            ['name' => 'Baleadas Doña María', 'category' => 0, 'city' => 'La Ceiba'],
            ['name' => 'Laboratorio Computec', 'category' => 1, 'city' => 'Tegucigalpa'],
            ['name' => 'Sastrería El Buen Vestir', 'category' => 4, 'city' => 'San Pedro Sula'],
            ['name' => 'AutoPartes Honduras', 'category' => 6, 'city' => 'Tegucigalpa'],
            ['name' => 'Panadería La Delicia', 'category' => 7, 'city' => 'La Ceiba'],
            ['name' => 'Dental Care', 'category' => 2, 'city' => 'San Pedro Sula'],
            ['name' => 'Mueblería La Moderna', 'category' => 5, 'city' => 'Tegucigalpa'],
            ['name' => 'Pizzería Napolitana', 'category' => 0, 'city' => 'San Pedro Sula'],
            ['name' => 'Electrodomésticos La Casa', 'category' => 5, 'city' => 'La Ceiba'],
            ['name' => 'Gimnasio FitZone', 'category' => 3, 'city' => 'Tegucigalpa'],
        ];

        $proTenants = [];
        foreach ($proBusinesses as $index => $business) {
            $seller = $sellers[$index % count($sellers)];
            $tenant = Tenant::create([
                'user_id' => $seller->id,
                'name' => $business['name'],
                'slug' => Str::slug($business['name']),
                'category_id' => $categoryIds[$business['category']],
                'description' => "Negocio profesional en {$business['city']}. Ofrecemos productos y servicios de la más alta calidad con años de experiencia en el mercado hondureño.",
                'city' => $business['city'],
                'status' => 'approved',
                'is_pro' => true,
                'logo_path' => 'https://images.unsplash.com/photo-' . (1500000000000 + $index * 10000000) . '?w=400&q=80',
                'primary_color' => $brandColors[array_rand($brandColors)],
                'whatsapp_number' => '+504 ' . rand(2000, 9999) . '-' . rand(1000, 9999),
                'whatsapp_template' => 'Hola, me interesa {product_name}. ¿Tienen disponibilidad?',
                'address' => 'Col. ' . ['Palmira', 'Kennedy', 'Las Colinas', 'El Centro'][array_rand(['Palmira', 'Kennedy', 'Las Colinas', 'El Centro'])] . ', ' . $business['city'],
            ]);
            $proTenants[] = $tenant;
        }

        $this->command->info('✅ 20 PRO businesses created');

        // 15 Free Businesses
        $freeBusinesses = [
            ['name' => 'Comedor Suyapa', 'category' => 0, 'city' => 'Tegucigalpa'],
            ['name' => 'Zapatería San Pedro', 'category' => 4, 'city' => 'San Pedro Sula'],
            ['name' => 'Tienda La Esquina', 'category' => 7, 'city' => 'La Ceiba'],
            ['name' => 'Barbería El Corte Perfecto', 'category' => 3, 'city' => 'Tegucigalpa'],
            ['name' => 'Frutería Tropicana', 'category' => 7, 'city' => 'San Pedro Sula'],
            ['name' => 'Librería Cervantes', 'category' => 3, 'city' => 'La Ceiba'],
            ['name' => 'Lavandería Express', 'category' => 3, 'city' => 'Tegucigalpa'],
            ['name' => 'Pastelería Sweet Dreams', 'category' => 0, 'city' => 'San Pedro Sula'],
            ['name' => 'Floristería Jardín', 'category' => 3, 'city' => 'La Ceiba'],
            ['name' => 'Jugos Naturales El Paraíso', 'category' => 0, 'city' => 'Tegucigalpa'],
            ['name' => 'Accesorios LaTina', 'category' => 4, 'city' => 'San Pedro Sula'],
            ['name' => 'Cerrajería La Llave', 'category' => 3, 'city' => 'La Ceiba'],
            ['name' => 'Óptica Visión Clara', 'category' => 2, 'city' => 'Tegucigalpa'],
            ['name' => 'Repostería Doña Lupita', 'category' => 0, 'city' => 'San Pedro Sula'],
            ['name' => 'Veterinaria Amigos Fieles', 'category' => 2, 'city' => 'La Ceiba'],
        ];

        $freeTenants = [];
        foreach ($freeBusinesses as $index => $business) {
            $seller = $sellers[($index + 20) % count($sellers)];
            $tenant = Tenant::create([
                'user_id' => $seller->id,
                'name' => $business['name'],
                'slug' => Str::slug($business['name']),
                'category_id' => $categoryIds[$business['category']],
                'description' => "Negocio familiar en {$business['city']} comprometido con la calidad y el servicio personalizado.",
                'city' => $business['city'],
                'status' => 'approved',
                'is_pro' => false,
                'logo_path' => 'https://images.unsplash.com/photo-' . (1600000000000 + $index * 10000000) . '?w=400&q=80',
                'primary_color' => $brandColors[array_rand($brandColors)],
                'whatsapp_number' => '+504 ' . rand(2000, 9999) . '-' . rand(1000, 9999),
                'address' => 'Barrio ' . ['La Hoya', 'El Centro', 'Los Pinos'][array_rand(['La Hoya', 'El Centro', 'Los Pinos'])] . ', ' . $business['city'],
            ]);
            $freeTenants[] = $tenant;
        }

        $this->command->info('✅ 15 Free businesses created');

        // 5 Pending Businesses
        $pendingBusinesses = [
            ['name' => 'Cafetería Express', 'category' => 0, 'city' => 'Tegucigalpa'],
            ['name' => 'Electrónica Digital', 'category' => 1, 'city' => 'San Pedro Sula'],
            ['name' => 'Consultorio Dr. Ramírez', 'category' => 2, 'city' => 'La Ceiba'],
            ['name' => 'Taller de Pintura AutoColor', 'category' => 6, 'city' => 'Tegucigalpa'],
            ['name' => 'Boutique Fashion Style', 'category' => 4, 'city' => 'San Pedro Sula'],
        ];

        foreach ($pendingBusinesses as $index => $business) {
            $seller = $sellers[($index + 25) % count($sellers)];
            $tenant = Tenant::create([
                'user_id' => $seller->id,
                'name' => $business['name'],
                'slug' => Str::slug($business['name']),
                'category_id' => $categoryIds[$business['category']],
                'description' => "Nuevo negocio en {$business['city']} esperando aprobación.",
                'city' => $business['city'],
                'status' => 'pending',
                'is_pro' => false,
                'logo_path' => null,
                'primary_color' => '#6b7280',
                'whatsapp_number' => '+504 ' . rand(2000, 9999) . '-' . rand(1000, 9999),
                'address' => $business['city'],
            ]);
        }

        $this->command->info('✅ 5 Pending businesses created');
        $this->command->info('✅ Total: 40 businesses created');

        // ==========================================
        // 4. CREATE PRODUCTS
        // ==========================================
        $this->command->info('Creating Products...');

        $productTemplates = [
            // Restaurantes
            ['Baleadas Tradicionales', 'L. 30', 50],
            ['Plato Típico Hondureño', 'L. 85', 120],
            ['Café Americano', 'L. 25', 35],
            ['Desayuno Completo', 'L. 95', 150],
            // Tecnología
            ['Laptop Dell i5', 'L. 15,000', 18000],
            ['Mouse Logitech', 'L. 250', 350],
            ['Teclado Mecánico', 'L. 800', 1200],
            ['Monitor 24"', 'L. 3,500', 4500],
            // Servicios
            ['Corte de Cabello', 'L. 80', 120],
            ['Manicure y Pedicure', 'L. 150', 200],
            ['Lavado de Ropa (kg)', 'L. 40', 60],
            // Moda
            ['Camisa Formal', 'L. 350', 550],
            ['Pantalón Jean', 'L. 400', 650],
            ['Zapatos Casuales', 'L. 600', 900],
            // Automotriz
            ['Cambio de Aceite', 'L. 250', 350],
            ['Alineación y Balanceo', 'L. 400', 600],
        ];

        $totalProducts = 0;
        // PRO tenants: 15 products each
        foreach ($proTenants as $tenant) {
            for ($i = 0; $i < 15; $i++) {
                $template = $productTemplates[array_rand($productTemplates)];
                $price = rand((int)$template[2] * 0.8, (int)$template[2] * 1.2);
                
                Product::create([
                    'tenant_id' => $tenant->id,
                    'name' => $template[0] . ' ' . ($i > 0 ? chr(65 + $i) : ''),
                    'slug' => Str::slug($template[0] . '-' . $tenant->id . '-' . $i),
                    'description' => 'Producto de alta calidad disponible en ' . $tenant->city,
                    'price' => $price,
                    'image_path' => 'https://images.unsplash.com/photo-' . (1550000000000 + ($tenant->id * 1000) + $i) . '?w=600&q=80',
                    'is_visible' => true,
                    'is_featured' => $i < 3,
                ]);
                $totalProducts++;
            }
        }

        // Free tenants: 5 products each
        foreach ($freeTenants as $tenant) {
            for ($i = 0; $i < 5; $i++) {
                $template = $productTemplates[array_rand($productTemplates)];
                $price = rand((int)$template[2] * 0.8, (int)$template[2] * 1.2);
                
                Product::create([
                    'tenant_id' => $tenant->id,
                    'name' => $template[0] . ($i > 0 ? ' ' . chr(65 + $i) : ''),
                    'slug' => Str::slug($template[0] . '-' . $tenant->id . '-' . $i),
                    'description' => 'Producto disponible en ' . $tenant->city,
                    'price' => $price,
                    'image_path' => 'https://images.unsplash.com/photo-' . (1550000000000 + ($tenant->id * 1000) + $i) . '?w=600&q=80',
                    'is_visible' => true,
                ]);
                $totalProducts++;
            }
        }

        $this->command->info("✅ {$totalProducts} products created (300 for PRO, 75 for Free)");

        // ==========================================
        // 5. CREATE 200 REVIEWS
        // ==========================================
        $this->command->info('Creating 200 Reviews...');

        $reviewComments = [
            'Excelente servicio, muy recomendado',
            'Productos de calidad y buen precio',
            'Atención rápida y profesional',
            'Muy satisfecho con la compra',
            'Lo mejor de la ciudad',
            'Buena relación calidad-precio',
            'Servicio impecable',
            'Volveré pronto',
            'Superó mis expectativas',
            '100% recomendado',
        ];

        $allTenants = array_merge($proTenants, $freeTenants);
        $reviewsCreated = 0;

        foreach ($allTenants as $tenant) {
            $numReviews = $tenant->is_pro ? rand(8, 12) : rand(2, 5);
            
            // Shuffle buyers to ensure uniqueness per tenant
            $tenantBuyers = $buyers->shuffle()->take($numReviews);
            
            foreach ($tenantBuyers as $buyer) {
                if ($reviewsCreated >= 200) break;

                $rating = $tenant->is_pro ? rand(4, 5) : rand(3, 5);
                
                Review::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $buyer->id,
                    'rating' => $rating,
                    'comment' => $reviewComments[array_rand($reviewComments)],
                ]);
                $reviewsCreated++;
            }
        }

        $this->command->info("✅ {$reviewsCreated} reviews created (PRO businesses avg 4.5-5★)");

        // ==========================================
        // SUMMARY
        // ==========================================
        $this->command->newLine();
        $this->command->info('🎉 Enterprise Data Seeding Complete!');
        $this->command->newLine();
        $this->command->table(
            ['Resource', 'Count', 'Details'],
            [
                ['Users', '81', '1 admin, 30 sellers, 50 buyers'],
                ['Categories', '8', 'With Lucide icons'],
                ['Businesses', '40', '20 PRO, 15 Free, 5 Pending'],
                ['Products', $totalProducts, '300 PRO products, 75 Free products'],
                ['Reviews', $reviewsCreated, 'Avg 4.5★ for PRO businesses'],
            ]
        );
        $this->command->newLine();
        $this->command->info('Login Credentials:');
        $this->command->info('  Super Admin: admin@conectahn.com / password');
        $this->command->info('  Example Seller: carlos-martinez@negocios.hn / password');
    }
}
