<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\City;
use App\Models\MenuSection;
use App\Models\OpeningHour;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

final class ProductionShowcaseTenantSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
        ]);

        $restaurant = $this->upsertTenant(
            slug: 'casa-brasa-bistro',
            businessName: 'Casa Brasa Bistro',
            ownerEmail: 'owner.casa-brasa-bistro@conectahn.local',
            categorySlug: 'restaurantes',
            citySlug: 'tegucigalpa',
            businessType: 'food',
            description: 'Restaurante con menu de autor, brunch y pedidos rapidos.',
            about: 'Un restaurante pensado para convertir visitas en pedidos, reservas y recompras.',
            slogan: 'Sabores que convierten la primera visita en cliente frecuente.',
            primaryColor: '#f97316',
            whatsapp: '50499990001',
            address: 'Boulevard Morazan, Tegucigalpa',
        );

        $menu = MenuSection::withoutEvents(fn () => MenuSection::query()->withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $restaurant->id, 'name' => 'Platos estrella'],
            ['is_active' => true, 'sort_order' => 1],
        ));

        Product::withoutEvents(fn () => Product::query()->withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $restaurant->id, 'slug' => 'brasa-signature-burger'],
            $this->payload('products', [
                'name' => 'Brasa Signature Burger',
                'description' => 'Hamburguesa premium con papas y salsa especial.',
                'price' => 289,
                'menu_section_id' => $menu->id,
                'is_visible' => true,
                'is_featured' => true,
                'stock' => 100,
                'is_active' => true,
            ]),
        ));

        $barbershop = $this->upsertTenant(
            slug: 'barberia-norte-pro',
            businessName: 'Barberia Norte Pro',
            ownerEmail: 'owner.barberia-norte-pro@conectahn.local',
            categorySlug: 'belleza',
            citySlug: 'san-pedro-sula',
            businessType: 'service',
            description: 'Barberia con agenda, grooming y promociones semanales.',
            about: 'Una barberia que convierte estilo, agenda y atencion rapida en una experiencia premium.',
            slogan: 'Cortes, barba y grooming con presencia profesional.',
            primaryColor: '#111827',
            whatsapp: '50499990005',
            address: 'Barrio Rio de Piedras, San Pedro Sula',
        );

        ServiceProvider::withoutEvents(fn () => ServiceProvider::query()->withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $barbershop->id, 'email' => 'marco.barber@conectahn.local'],
            $this->payload('service_providers', [
                'name' => 'Marco Diaz',
                'phone' => '50499990055',
                'specialty' => 'Barberia',
                'is_active' => true,
            ]),
        ));

        Service::withoutEvents(fn () => Service::query()->withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $barbershop->id, 'name' => 'Corte premium con barba'],
            $this->payload('services', [
                'description' => 'Sesion completa de corte, perfilado y grooming.',
                'duration_minutes' => 50,
                'price' => 320,
                'is_active' => true,
            ]),
        ));

        $legalOffice = $this->upsertTenant(
            slug: 'bufete-lex-hn',
            businessName: 'Bufete Lex HN',
            ownerEmail: 'owner.bufete-lex-hn@conectahn.local',
            categorySlug: 'servicios',
            citySlug: 'tegucigalpa',
            businessType: 'service',
            description: 'Bufete legal con asesoria corporativa y acompanamiento judicial.',
            about: 'Servicios legales enfocados en cumplimiento, contratos y defensa corporativa.',
            slogan: 'Asesoria legal clara para decisiones de negocio confiables.',
            primaryColor: '#0f172a',
            whatsapp: '50499990009',
            address: 'Colonia Palmira, Tegucigalpa',
        );

        ServiceProvider::withoutEvents(fn () => ServiceProvider::query()->withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $legalOffice->id, 'email' => 'socia.lex@conectahn.local'],
            $this->payload('service_providers', [
                'name' => 'Maria Fernanda Sosa',
                'phone' => '50499990077',
                'specialty' => 'Derecho Corporativo',
                'is_active' => true,
            ]),
        ));

        Service::withoutEvents(fn () => Service::query()->withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $legalOffice->id, 'name' => 'Revision y redaccion de contratos'],
            $this->payload('services', [
                'description' => 'Analisis legal integral de contratos comerciales con recomendaciones accionables.',
                'duration_minutes' => 60,
                'price' => 1200,
                'is_active' => true,
            ]),
        ));

        $this->seedOpeningHours($restaurant);
        $this->seedOpeningHours($barbershop);
        $this->seedOpeningHours($legalOffice);

        $this->command?->info('Production showcase tenants are ready and active.');
    }

    private function upsertTenant(
        string $slug,
        string $businessName,
        string $ownerEmail,
        string $categorySlug,
        string $citySlug,
        string $businessType,
        string $description,
        string $about,
        string $slogan,
        string $primaryColor,
        string $whatsapp,
        string $address,
    ): Tenant {
        $owner = User::query()->firstOrCreate(
            ['email' => $ownerEmail],
            [
                'name' => $businessName.' Owner',
                'password' => Hash::make(\Illuminate\Support\Str::random(32)),
                'email_verified_at' => now(),
            ],
        );

        $categoryId = Category::query()->where('slug', $categorySlug)->value('id');
        $cityId = City::query()->where('slug', $citySlug)->value('id')
            ?? City::query()->value('id');

        $tenant = Tenant::withoutEvents(fn () => Tenant::query()->updateOrCreate(
            ['slug' => $slug],
            $this->payload('tenants', [
                'user_id' => $owner->id,
                'name' => $businessName,
                'description' => $description,
                'about' => $about,
                'slogan' => $slogan,
                'address' => $address,
                'city_id' => $cityId,
                'category_id' => $categoryId,
                'whatsapp_number' => $whatsapp,
                'phone' => $whatsapp,
                'primary_color' => $primaryColor,
                'brand_color' => $primaryColor,
                'business_type' => $businessType,
                'status' => 'approved',
                'is_active' => true,
                'plan_type' => 'pro',
                'subscription_status' => 'active',
                'subscription_ends_at' => now()->addMonths(6),
                'product_limit' => 500,
            ]),
        ));

        if ((string) $owner->tenant_id !== (string) $tenant->id) {
            $owner->forceFill(['tenant_id' => $tenant->id])->save();
        }

        return $tenant;
    }

    private function seedOpeningHours(Tenant $tenant): void
    {
        foreach (range(0, 6) as $dayOfWeek) {
            OpeningHour::query()->withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'day_of_week' => $dayOfWeek],
                $this->payload('opening_hours', ['opening_time' => '09:00:00', 'closing_time' => '18:00:00']),
            );
        }
    }

    /**
     * Keep the seeder resilient when production schema lags behind local schema.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function payload(string $table, array $attributes): array
    {
        $result = [];

        foreach ($attributes as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $result[$column] = $value;
            }
        }

        return $result;
    }
}
