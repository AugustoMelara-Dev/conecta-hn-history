<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

final class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $hasColor = Schema::hasColumn('categories', 'color');
        $hasSortOrder = Schema::hasColumn('categories', 'sort_order');
        $hasIsFeatured = Schema::hasColumn('categories', 'is_featured');

        $categories = [
            [
                'name' => 'Restaurantes',
                'slug' => 'restaurantes',
                'icon' => 'utensils',
                'color' => '#f97316',
                'sort_order' => 10,
                'is_active' => true,
                'is_visible' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Tecnologia',
                'slug' => 'tecnologia',
                'icon' => 'laptop',
                'color' => '#3b82f6',
                'sort_order' => 20,
                'is_active' => true,
                'is_visible' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Salud',
                'slug' => 'salud',
                'icon' => 'heart-pulse',
                'color' => '#10b981',
                'sort_order' => 30,
                'is_active' => true,
                'is_visible' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Moda',
                'slug' => 'moda',
                'icon' => 'shirt',
                'color' => '#ec4899',
                'sort_order' => 40,
                'is_active' => true,
                'is_visible' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Hogar',
                'slug' => 'hogar',
                'icon' => 'house',
                'color' => '#8b5cf6',
                'sort_order' => 50,
                'is_active' => true,
                'is_visible' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Servicios',
                'slug' => 'servicios',
                'icon' => 'briefcase',
                'color' => '#64748b',
                'sort_order' => 60,
                'is_active' => true,
                'is_visible' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Belleza',
                'slug' => 'belleza',
                'icon' => 'sparkles',
                'color' => '#d946ef',
                'sort_order' => 70,
                'is_active' => true,
                'is_visible' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Automotriz',
                'slug' => 'automotriz',
                'icon' => 'car',
                'color' => '#ef4444',
                'sort_order' => 80,
                'is_active' => true,
                'is_visible' => true,
                'is_featured' => false,
            ],
        ];

        foreach ($categories as $category) {
            $payload = [
                'name' => $category['name'],
                'slug' => $category['slug'],
                'icon' => $category['icon'],
                'is_active' => $category['is_active'],
            ];

            if ($hasColor) {
                $payload['color'] = $category['color'];
            }

            if ($hasSortOrder) {
                $payload['sort_order'] = $category['sort_order'];
            }

            if ($hasIsFeatured) {
                $payload['is_featured'] = $category['is_featured'];
            }

            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $payload,
            );
        }

        $this->command?->info('Reference categories seeded successfully.');
    }
}
