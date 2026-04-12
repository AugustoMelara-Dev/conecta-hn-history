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

        $catalog = [
            [
                'tenant' => [
                    'slug' => 'casa-brasa-bistro',
                    'businessName' => 'Casa Brasa Bistro',
                    'ownerEmail' => 'owner.casa-brasa-bistro@conectahn.local',
                    'categorySlug' => 'restaurantes',
                    'citySlug' => 'tegucigalpa',
                    'businessType' => 'food',
                    'description' => 'Restaurante con menu de autor, brunch y pedidos rapidos.',
                    'about' => 'Un restaurante pensado para convertir visitas en pedidos, reservas y recompras.',
                    'slogan' => 'Sabores que convierten la primera visita en cliente frecuente.',
                    'primaryColor' => '#f97316',
                    'whatsapp' => '50499990001',
                    'address' => 'Boulevard Morazan, Tegucigalpa',
                ],
                'products' => [[
                    'slug' => 'brasa-signature-burger',
                    'name' => 'Brasa Signature Burger',
                    'description' => 'Hamburguesa premium con papas y salsa especial.',
                    'price' => 289,
                    'section' => 'Platos estrella',
                ]],
            ],
            [
                'tenant' => [
                    'slug' => 'patio-casual-bistro',
                    'businessName' => 'Patio Casual Bistro',
                    'ownerEmail' => 'owner.patio-casual-bistro@conectahn.local',
                    'categorySlug' => 'restaurantes',
                    'citySlug' => 'san-pedro-sula',
                    'businessType' => 'food',
                    'description' => 'Restaurante casual con burgers, lunch specials y combos de alto ticket.',
                    'about' => 'Un bistro casual pensado para mover platos estrella, combos y recompra semanal.',
                    'slogan' => 'Combos, antojo y pedidos rapidos para volver siempre.',
                    'primaryColor' => '#ea580c',
                    'whatsapp' => '50499990008',
                    'address' => 'Boulevard Mackay, San Pedro Sula',
                ],
                'products' => [[
                    'slug' => 'burger-combo-de-la-casa',
                    'name' => 'Burger combo de la casa',
                    'description' => 'Burger premium con papas y bebida para subir ticket desde la primera orden.',
                    'price' => 245,
                    'section' => 'Combos casuales',
                ]],
            ],
            [
                'tenant' => [
                    'slug' => 'alto-brew-cafeteria',
                    'businessName' => 'Alto Brew Cafeteria',
                    'ownerEmail' => 'owner.alto-brew-cafeteria@conectahn.local',
                    'categorySlug' => 'restaurantes',
                    'citySlug' => 'tegucigalpa',
                    'businessType' => 'food',
                    'description' => 'Cafeteria de especialidad con espresso, brunch y pickup rapido.',
                    'about' => 'Una cafeteria que mezcla marca, comunidad y una carta pensada para la rutina diaria.',
                    'slogan' => 'Cafe de especialidad, brunch y pedidos ligeros sin filas.',
                    'primaryColor' => '#7c3aed',
                    'whatsapp' => '50499990009',
                    'address' => 'Colonia Florencia, Tegucigalpa',
                ],
                'products' => [[
                    'slug' => 'cold-brew-de-temporada',
                    'name' => 'Cold brew de temporada',
                    'description' => 'Bebida firma para manana, tarde o pickup express.',
                    'price' => 110,
                    'section' => 'Especialidades y brunch',
                ]],
            ],
            [
                'tenant' => [
                    'slug' => 'movil-center-hn',
                    'businessName' => 'Movil Center HN',
                    'ownerEmail' => 'owner.movil-center-hn@conectahn.local',
                    'categorySlug' => 'tecnologia',
                    'citySlug' => 'san-pedro-sula',
                    'businessType' => 'retail',
                    'description' => 'Tienda de celulares, accesorios y soporte tecnico para equipos modernos.',
                    'about' => 'Combinamos venta de smartphones, accesorios y servicio tecnico en una vitrina lista para vender.',
                    'slogan' => 'Equipos, accesorios y soporte en una sola experiencia.',
                    'primaryColor' => '#2563eb',
                    'whatsapp' => '50499990002',
                    'address' => 'Avenida Circunvalacion, San Pedro Sula',
                ],
                'products' => [[
                    'slug' => 'iphone-15-pro-256gb',
                    'name' => 'iPhone 15 Pro 256GB',
                    'description' => 'Equipo premium con garantia local y entrega inmediata.',
                    'price' => 34999,
                ]],
            ],
            [
                'tenant' => [
                    'slug' => 'titan-flagship-mobile',
                    'businessName' => 'Titan Flagship Mobile',
                    'ownerEmail' => 'owner.titan-flagship-mobile@conectahn.local',
                    'categorySlug' => 'tecnologia',
                    'citySlug' => 'tegucigalpa',
                    'businessType' => 'retail',
                    'description' => 'Tienda de celulares premium con flagships, bundles y soporte posventa.',
                    'about' => 'Retail de tecnologia para equipos de gama alta, accesorios premium y una experiencia de compra mas confiable.',
                    'slogan' => 'Flagships, bundles y soporte que justifican el ticket.',
                    'primaryColor' => '#1d4ed8',
                    'whatsapp' => '50499990010',
                    'address' => 'Boulevard Juan Pablo II, Tegucigalpa',
                ],
                'products' => [[
                    'slug' => 'galaxy-ultra-flagship-bundle',
                    'name' => 'Galaxy Ultra flagship bundle',
                    'description' => 'Equipo de gama alta con bundle MagSafe y garantia local.',
                    'price' => 38999,
                ]],
            ],
            [
                'tenant' => [
                    'slug' => 'ferreteria-ferremax-hn',
                    'businessName' => 'Ferreteria FerreMax HN',
                    'ownerEmail' => 'owner.ferreteria-ferremax-hn@conectahn.local',
                    'categorySlug' => 'hogar',
                    'citySlug' => 'la-ceiba',
                    'businessType' => 'retail',
                    'description' => 'Ferreteria con herramientas, materiales y cotizacion rapida para proyectos y reparaciones.',
                    'about' => 'Una ferreteria pensada para ventas por proyecto, mantenimiento y urgencias del dia a dia.',
                    'slogan' => 'Herramientas, materiales y respuesta comercial sin vueltas.',
                    'primaryColor' => '#f59e0b',
                    'whatsapp' => '50499990011',
                    'address' => 'Avenida 14 de Julio, La Ceiba',
                ],
                'products' => [[
                    'slug' => 'kit-de-herramientas-para-obra',
                    'name' => 'Kit de herramientas para obra',
                    'description' => 'Bundle de herramientas basicas para mantenimiento y construccion ligera.',
                    'price' => 1890,
                ]],
            ],
            [
                'tenant' => [
                    'slug' => 'taller-motor-pro',
                    'businessName' => 'Taller Motor Pro',
                    'ownerEmail' => 'owner.taller-motor-pro@conectahn.local',
                    'categorySlug' => 'automotriz',
                    'citySlug' => 'tegucigalpa',
                    'businessType' => 'hybrid',
                    'description' => 'Taller automotriz con diagnostico, servicios y repuestos visibles.',
                    'about' => 'Negocio hibrido para diagnostico, mantenimiento y venta de repuestos.',
                    'slogan' => 'Diagnostico tecnico con repuestos y contacto directo.',
                    'primaryColor' => '#dc2626',
                    'whatsapp' => '50499990006',
                    'address' => 'Anillo periferico, Tegucigalpa',
                ],
                'products' => [[
                    'slug' => 'filtro-premium-sedan',
                    'name' => 'Filtro premium para sedan',
                    'description' => 'Repuesto listo para taller y venta directa.',
                    'price' => 390,
                ]],
            ],
            [
                'tenant' => [
                    'slug' => 'repuestos-turbo-center',
                    'businessName' => 'Repuestos Turbo Center',
                    'ownerEmail' => 'owner.repuestos-turbo-center@conectahn.local',
                    'categorySlug' => 'automotriz',
                    'citySlug' => 'tegucigalpa',
                    'businessType' => 'hybrid',
                    'description' => 'Tienda de repuestos con autopartes, diagnostico e instalacion para vehiculos livianos.',
                    'about' => 'Negocio hibrido para vender piezas, orientar compatibilidad y resolver soporte tecnico desde un solo frente.',
                    'slogan' => 'Autopartes, diagnostico y soluciones en una sola ruta.',
                    'primaryColor' => '#dc2626',
                    'whatsapp' => '50499990017',
                    'address' => 'Salida a Olancho, Tegucigalpa',
                ],
                'products' => [[
                    'slug' => 'kit-premium-de-frenos-delanteros',
                    'name' => 'Kit premium de frenos delanteros',
                    'description' => 'Autopartes para mantenimiento preventivo con alto movimiento.',
                    'price' => 2450,
                ]],
                'serviceProvider' => ['name' => 'Carlos Erazo', 'email' => 'carlos.repuestos@conectahn.local', 'phone' => '50499990017', 'specialty' => 'Soporte tecnico'],
                'service' => ['name' => 'Revision de compatibilidad y montaje', 'description' => 'Chequeo inicial para validar pieza, compatibilidad y propuesta de instalacion.', 'duration' => 35, 'price' => 420],
            ],
            [
                'tenant' => [
                    'slug' => 'bufete-lex-hn',
                    'businessName' => 'Bufete Lex HN',
                    'ownerEmail' => 'owner.bufete-lex-hn@conectahn.local',
                    'categorySlug' => 'servicios',
                    'citySlug' => 'tegucigalpa',
                    'businessType' => 'service',
                    'description' => 'Firma legal para consultas civiles, familiares y mercantiles.',
                    'about' => 'Servicios legales para personas y pymes con orientacion clara y accionable.',
                    'slogan' => 'Asesoria legal clara para resolver casos del dia a dia.',
                    'primaryColor' => '#0f172a',
                    'whatsapp' => '50499990003',
                    'address' => 'Colonia Palmira, Tegucigalpa',
                ],
                'serviceProvider' => ['name' => 'Ana Lopez', 'email' => 'ana.lex@conectahn.local', 'phone' => '50499990003', 'specialty' => 'Derecho Corporativo'],
                'service' => ['name' => 'Consulta legal inicial', 'description' => 'Sesion para revisar tu caso y definir siguientes pasos.', 'duration' => 60, 'price' => 950],
            ],
            [
                'tenant' => [
                    'slug' => 'lex-corporativo-hn',
                    'businessName' => 'Lex Corporativo HN',
                    'ownerEmail' => 'owner.lex-corporativo-hn@conectahn.local',
                    'categorySlug' => 'servicios',
                    'citySlug' => 'san-pedro-sula',
                    'businessType' => 'service',
                    'description' => 'Bufete corporativo para compliance, contratos empresariales y mercantil.',
                    'about' => 'Firma legal enfocada en clientes empresa, con intake serio y servicios corporativos claros.',
                    'slogan' => 'Asesoria corporativa con criterio de negocio y respuesta clara.',
                    'primaryColor' => '#0f172a',
                    'whatsapp' => '50499990013',
                    'address' => 'Colonia Trejo, San Pedro Sula',
                ],
                'serviceProvider' => ['name' => 'Andrea Carias', 'email' => 'andrea.corporativo@conectahn.local', 'phone' => '50499990013', 'specialty' => 'Derecho Corporativo'],
                'service' => ['name' => 'Sesion de diagnostico corporativo', 'description' => 'Revision inicial para compliance, contratos y estructura legal de la empresa.', 'duration' => 60, 'price' => 1500],
            ],
            [
                'tenant' => [
                    'slug' => 'clinica-salud-vital',
                    'businessName' => 'Clinica Salud Vital',
                    'ownerEmail' => 'owner.clinica-salud-vital@conectahn.local',
                    'categorySlug' => 'salud',
                    'citySlug' => 'la-ceiba',
                    'businessType' => 'service',
                    'description' => 'Clinica integral con evaluaciones, programas de salud y agenda visible.',
                    'about' => 'Centro de salud con enfoque en confianza, agenda y orientacion rapida para pacientes.',
                    'slogan' => 'Reserva evaluaciones con una experiencia clara y confiable.',
                    'primaryColor' => '#059669',
                    'whatsapp' => '50499990004',
                    'address' => 'Avenida San Isidro, La Ceiba',
                ],
                'serviceProvider' => ['name' => 'Dra. Sofia Pineda', 'email' => 'sofia.salud@conectahn.local', 'phone' => '50499990004', 'specialty' => 'Medicina General'],
                'service' => ['name' => 'Evaluacion general', 'description' => 'Consulta inicial para diagnostico y seguimiento.', 'duration' => 45, 'price' => 700],
            ],
            [
                'tenant' => [
                    'slug' => 'clinica-dental-sonrisa',
                    'businessName' => 'Clinica Dental Sonrisa',
                    'ownerEmail' => 'owner.clinica-dental-sonrisa@conectahn.local',
                    'categorySlug' => 'salud',
                    'citySlug' => 'tegucigalpa',
                    'businessType' => 'service',
                    'description' => 'Clinica dental con valoracion, ortodoncia y tratamientos visibles desde la portada.',
                    'about' => 'Una clinica dental que orienta mejor al paciente y vuelve la agenda un proceso mas claro.',
                    'slogan' => 'Valoraciones claras para una sonrisa mejor cuidada.',
                    'primaryColor' => '#14b8a6',
                    'whatsapp' => '50499990012',
                    'address' => 'Colonia Tepeyac, Tegucigalpa',
                ],
                'serviceProvider' => ['name' => 'Dra. Mariana Rosales', 'email' => 'mariana.dental@conectahn.local', 'phone' => '50499990012', 'specialty' => 'Odontologia'],
                'service' => ['name' => 'Valoracion odontologica', 'description' => 'Revision inicial para diagnostico, presupuesto y siguiente paso clinico.', 'duration' => 45, 'price' => 850],
            ],
            [
                'tenant' => [
                    'slug' => 'faro-growth-advisory',
                    'businessName' => 'Faro Growth Advisory',
                    'ownerEmail' => 'owner.faro-growth-advisory@conectahn.local',
                    'categorySlug' => 'servicios',
                    'citySlug' => 'tegucigalpa',
                    'businessType' => 'service',
                    'description' => 'Consultoria comercial y operativa para pymes que quieren crecer con mejor ejecucion.',
                    'about' => 'Ayudamos a negocios a ordenar ventas, procesos y acompanamiento comercial.',
                    'slogan' => 'Diagnostico, estrategia y acompanamiento para crecer con mejor foco.',
                    'primaryColor' => '#1d4ed8',
                    'whatsapp' => '50499990007',
                    'address' => 'Lomas del Guijarro, Tegucigalpa',
                ],
                'serviceProvider' => ['name' => 'Daniel Funez', 'email' => 'daniel.faro@conectahn.local', 'phone' => '50499990007', 'specialty' => 'Estrategia Comercial'],
                'service' => ['name' => 'Discovery call comercial', 'description' => 'Sesion para diagnosticar cuellos de botella y definir una hoja de ruta.', 'duration' => 50, 'price' => 1200],
            ],
            [
                'tenant' => [
                    'slug' => 'faro-finanzas-advisory',
                    'businessName' => 'Faro Finanzas Advisory',
                    'ownerEmail' => 'owner.faro-finanzas-advisory@conectahn.local',
                    'categorySlug' => 'servicios',
                    'citySlug' => 'tegucigalpa',
                    'businessType' => 'service',
                    'description' => 'Consultoria financiera para flujo de caja, rentabilidad y acompanamiento ejecutivo.',
                    'about' => 'Ayudamos a pymes a entender su caja y tomar decisiones mejores.',
                    'slogan' => 'Flujo de caja, rentabilidad y decisiones con mas claridad.',
                    'primaryColor' => '#1e40af',
                    'whatsapp' => '50499990014',
                    'address' => 'Lomas del Mayab, Tegucigalpa',
                ],
                'serviceProvider' => ['name' => 'Rene Ponce', 'email' => 'rene.finanzas@conectahn.local', 'phone' => '50499990014', 'specialty' => 'Consultoria Financiera'],
                'service' => ['name' => 'Diagnostico de flujo de caja', 'description' => 'Sesion para revisar caja, margen y prioridades financieras de la empresa.', 'duration' => 55, 'price' => 1300],
            ],
            [
                'tenant' => [
                    'slug' => 'barberia-norte-pro',
                    'businessName' => 'Barberia Norte Pro',
                    'ownerEmail' => 'owner.barberia-norte-pro@conectahn.local',
                    'categorySlug' => 'belleza',
                    'citySlug' => 'san-pedro-sula',
                    'businessType' => 'service',
                    'description' => 'Barberia con agenda, grooming y promociones semanales.',
                    'about' => 'Una barberia que convierte estilo, agenda y atencion rapida en una experiencia premium.',
                    'slogan' => 'Cortes, barba y grooming con presencia profesional.',
                    'primaryColor' => '#111827',
                    'whatsapp' => '50499990005',
                    'address' => 'Barrio Rio de Piedras, San Pedro Sula',
                ],
                'serviceProvider' => ['name' => 'Marco Diaz', 'email' => 'marco.barber@conectahn.local', 'phone' => '50499990005', 'specialty' => 'Barberia'],
                'service' => ['name' => 'Corte premium con barba', 'description' => 'Sesion completa de corte, perfilado y grooming.', 'duration' => 50, 'price' => 320],
            ],
            [
                'tenant' => [
                    'slug' => 'district-barber-lounge',
                    'businessName' => 'District Barber Lounge',
                    'ownerEmail' => 'owner.district-barber-lounge@conectahn.local',
                    'categorySlug' => 'belleza',
                    'citySlug' => 'san-pedro-sula',
                    'businessType' => 'service',
                    'description' => 'Barber lounge premium con grooming, slots premium y atencion aspiracional.',
                    'about' => 'Una barber premium donde marca, agenda y ticket viven como una experiencia.',
                    'slogan' => 'Grooming premium con estilo, agenda y presencia de marca.',
                    'primaryColor' => '#111827',
                    'whatsapp' => '50499990015',
                    'address' => 'Colonia Moderna, San Pedro Sula',
                ],
                'serviceProvider' => ['name' => 'Jorge Galeas', 'email' => 'jorge.lounge@conectahn.local', 'phone' => '50499990015', 'specialty' => 'Barber Premium'],
                'service' => ['name' => 'Ritual premium de barberia', 'description' => 'Corte, barba, hot towel y grooming completo en una sola sesion.', 'duration' => 60, 'price' => 450],
            ],
            [
                'tenant' => [
                    'slug' => 'aura-spa-studio',
                    'businessName' => 'Aura Spa Studio',
                    'ownerEmail' => 'owner.aura-spa-studio@conectahn.local',
                    'categorySlug' => 'belleza',
                    'citySlug' => 'la-ceiba',
                    'businessType' => 'service',
                    'description' => 'Spa studio con masajes, faciales y paquetes de bienestar premium.',
                    'about' => 'Un estudio de bienestar que combina rituales, agenda y narrativa sensorial.',
                    'slogan' => 'Bienestar premium para volver una pausa en rutina.',
                    'primaryColor' => '#a855f7',
                    'whatsapp' => '50499990016',
                    'address' => 'Zona Viva, La Ceiba',
                ],
                'serviceProvider' => ['name' => 'Valeria Soto', 'email' => 'valeria.spa@conectahn.local', 'phone' => '50499990016', 'specialty' => 'Bienestar'],
                'service' => ['name' => 'Ritual facial y relajacion', 'description' => 'Sesion de bienestar con facial, masaje corto y cierre premium.', 'duration' => 70, 'price' => 980],
            ],
        ];

        foreach ($catalog as $item) {
            $tenantData = $item['tenant'];
            $tenant = $this->upsertTenant(
                slug: $tenantData['slug'],
                businessName: $tenantData['businessName'],
                ownerEmail: $tenantData['ownerEmail'],
                categorySlug: $tenantData['categorySlug'],
                citySlug: $tenantData['citySlug'],
                businessType: $tenantData['businessType'],
                description: $tenantData['description'],
                about: $tenantData['about'],
                slogan: $tenantData['slogan'],
                primaryColor: $tenantData['primaryColor'],
                whatsapp: $tenantData['whatsapp'],
                address: $tenantData['address'],
            );

            foreach (($item['products'] ?? []) as $product) {
                $menuSectionId = null;
                if (isset($product['section']) && is_string($product['section']) && $product['section'] !== '') {
                    $menu = MenuSection::withoutEvents(fn () => MenuSection::query()->withoutGlobalScopes()->firstOrCreate(
                        ['tenant_id' => $tenant->id, 'name' => $product['section']],
                        ['is_active' => true, 'sort_order' => 1],
                    ));
                    $menuSectionId = $menu->id;
                }

                Product::withoutEvents(fn () => Product::query()->withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $product['slug']],
                    $this->payload('products', [
                        'name' => $product['name'],
                        'description' => $product['description'],
                        'price' => $product['price'],
                        'menu_section_id' => $menuSectionId,
                        'is_visible' => true,
                        'is_featured' => true,
                        'stock' => 100,
                        'is_active' => true,
                    ]),
                ));
            }

            if (isset($item['serviceProvider']) && is_array($item['serviceProvider'])) {
                $provider = $item['serviceProvider'];

                ServiceProvider::withoutEvents(fn () => ServiceProvider::query()->withoutGlobalScopes()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'email' => $provider['email']],
                    $this->payload('service_providers', [
                        'name' => $provider['name'],
                        'phone' => $provider['phone'],
                        'specialty' => $provider['specialty'],
                        'is_active' => true,
                    ]),
                ));
            }

            if (isset($item['service']) && is_array($item['service'])) {
                $service = $item['service'];

                Service::withoutEvents(fn () => Service::query()->withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $service['name']],
                    $this->payload('services', [
                        'description' => $service['description'],
                        'duration_minutes' => $service['duration'],
                        'price' => $service['price'],
                        'is_active' => true,
                    ]),
                ));
            }

            $this->seedOpeningHours($tenant);
        }

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
