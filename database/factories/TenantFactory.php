<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * TenantFactory - Enterprise-Grade Factory for Multi-Tenant Testing
 *
 * DESIGN DECISIONS:
 * - Foreign keys (city_id, category_id) are nullable in DB, so defaults to null
 * - User is always required (non-nullable FK) - uses UserFactory
 * - Optional relationships can be added via state modifiers
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Honduran business name prefixes for realistic names
     */
    private array $honduranPrefixes = [
        'Inversiones', 'Comercial', 'Distribuidora', 'Importadora', 'Exportadora',
        'Grupo', 'Corporación', 'Empresa', 'Servicios', 'Soluciones',
    ];

    private array $honduranSuffixes = [
        'Catracho', 'Hondureño', 'Lenca', 'Copán', 'Valle', 'Central',
        'del Norte', 'del Sur', 'Tropical', 'Colonial',
    ];

    private array $businessTypes = [
        'Restaurante', 'Comedor', 'Café', 'Pizzería',
        'Tech', 'Digital', 'Systems', 'Solutions',
        'Servicios', 'Taller', 'Centro', 'Estudio',
        'Tienda', 'Boutique', 'Almacén', 'Distribuidora',
    ];

    /**
     * Define the model's default state.
     *
     * CRITICAL: This method must be self-contained and not query the database
     * for existing records, as tests run with RefreshDatabase (empty tables).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $baseName = $this->faker->randomElement($this->businessTypes);
        $suffix = $this->faker->randomElement($this->honduranSuffixes);
        $name = "{$baseName} {$suffix}";

        return [
            // Required FK - User always created via factory chain
            'user_id' => User::factory(),

            // Optional FKs - Default to null (columns are nullable in migration)
            // Use state modifiers like ->withCity() or ->withCategory() when needed
            'city_id' => null,
            'category_id' => null,

            // Core identity
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'description' => $this->faker->paragraph(),
            'address' => $this->faker->streetAddress(),

            // Branding (optional fields)
            'logo_path' => null,
            'banner_path' => null,
            'cover_image_path' => null,
            'blur_hash' => null,
            'primary_color' => $this->faker->randomElement([
                '#000000', '#1e40af', '#7c3aed', '#dc2626', '#059669', '#f59e0b',
            ]),
            'theme_config' => null,

            // Contact (minimal required for testing)
            'whatsapp_number' => '+504 '.$this->faker->numerify('####-####'),
            'whatsapp_message' => null,
            'whatsapp_template' => 'Hola, me interesa conocer más sobre sus productos.',
            'social_links' => null,

            // Status & Config
            'is_pro' => false,
            'is_active' => true,
            'status' => 'approved',
            'plan_expires_at' => null,
            'product_limit' => 10,

            // Operational
            'hours_data' => null,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // STATE MODIFIERS - Chain these to add optional relationships
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Attach a Category to the tenant.
     * Creates a new Category via factory if none provided.
     */
    public function withCategory(?Category $category = null): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category?->id ?? Category::factory(),
        ]);
    }

    /**
     * Attach a City to the tenant.
     * Creates a new City via factory chain (City->Municipality->Department) if none provided.
     */
    public function withCity(?City $city = null): static
    {
        return $this->state(fn (array $attributes) => [
            'city_id' => $city?->id ?? City::factory(),
        ]);
    }

    /**
     * Mark tenant as PRO tier.
     */
    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pro' => true,
        ]);
    }

    /**
     * Mark tenant as pending approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Mark tenant as approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }

    /**
     * Mark tenant as rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }

    /**
     * Mark tenant as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
