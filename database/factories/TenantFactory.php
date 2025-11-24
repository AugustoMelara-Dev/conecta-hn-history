<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Honduran business name prefixes for realistic names
     */
    private $honduranPrefixes = [
        'Inversiones', 'Comercial', 'Distribuidora', 'Importadora', 'Exportadora',
        'Grupo', 'Corporación', 'Empresa', 'Servicios', 'Soluciones',
    ];

    private $honduranSuffixes = [
        'Catracho', 'Hondureño', 'Lenca', 'Copán', 'Valle', 'Central',
        'del Norte', 'del Sur', 'Tropical', 'Colonial',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $businessType = $this->faker->randomElement([
            'restaurant' => ['Restaurante', 'Comedor', 'Café', 'Pizzería'],
            'tech' => ['Tech', 'Digital', 'Systems', 'Solutions'],
            'services' => ['Servicios', 'Taller', 'Centro', 'Estudio'],
            'retail' => ['Tienda', 'Boutique', 'Almacén', 'Distribuidora'],
        ]);

        $category = Category::inRandomOrder()->first();
        $baseName = $this->faker->randomElement($businessType);
        $name = $baseName.' '.$this->faker->randomElement(array_merge(['La Esperanza', 'El Progreso', 'San Pedro', 'Santa Rosa', 'Los Andes'], $this->honduranSuffixes));

        $unsplashImages = [
            'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=1200&q=80', // Restaurant
            'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=1200&q=80', // Store
            'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=1200&q=80', // Office
            'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=1200&q=80', // Tech
            'https://images.unsplash.com/photo-1542744173-8e7e53415bb0?w=1200&q=80', // Business
            'https://images.unsplash.com/photo-1497366216548-37526070297c?w=1200&q=80', // Office
        ];

        return [
            'user_id' => User::factory(),
            'category_id' => $category?->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'category' => $category?->slug,
            'logo_path' => $this->faker->randomElement($unsplashImages), // Use Unsplash for logo
            'primary_color' => $this->faker->randomElement(['#000000', '#1e40af', '#7c3aed', '#dc2626', '#059669', '#f59e0b']),
            'is_pro' => false,
            'status' => 'approved',
            'whatsapp_number' => '+504 '.$this->faker->numerify('####-####'),
            'whatsapp_template' => 'Hola, me interesa conocer más sobre sus productos y servicios.',
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->randomElement(['Tegucigalpa', 'San Pedro Sula', 'La Ceiba', 'Choluteca', 'Comayagua']),
            'documents_path' => null,
            'description' => $this->faker->paragraph(),
        ];
    }

    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pro' => true,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }
}
