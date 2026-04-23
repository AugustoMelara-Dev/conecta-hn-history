<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Shared\Services\TenantContext;
use App\Models\Category;
use App\Models\City;
use App\Models\Department;
use App\Models\MenuSection;
use App\Models\Municipality;
use App\Models\OpeningHour;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class E2EStorefrontSeeder extends Seeder
{
    public function run(): void
    {
        $this->initializeConsoleTenantContext();

        $this->call(CategorySeeder::class);

        $cities = [
            'tegucigalpa' => $this->createCity('Francisco Morazan', 'Distrito Central', 'Tegucigalpa', 14.0723, -87.1921),
            'san-pedro-sula' => $this->createCity('Cortes', 'San Pedro Sula', 'San Pedro Sula', 15.5042, -88.0250),
            'la-ceiba' => $this->createCity('Atlantida', 'La Ceiba', 'La Ceiba', 15.7597, -86.7822),
        ];

        $categories = Category::query()
            ->whereIn('slug', ['restaurantes', 'tecnologia', 'salud', 'belleza', 'servicios', 'automotriz', 'hogar'])
            ->get()
            ->keyBy('slug');

        $officialDirectoryFixture = $this->createTenant([
            'name' => 'Demo AI Guide HN',
            'slug' => 'demo-ai-guide-hn',
            'business_type' => 'food',
            'category_id' => $categories['restaurantes']->id,
            'city_id' => $cities['tegucigalpa']->id,
            'description' => 'Fixture oficial de directorio/IA para release-critical.',
            'about' => 'Tenant persistente para validar recomendaciones por presupuesto y visibilidad publicada.',
            'slogan' => 'Directorio IA con presupuesto real.',
            'primary_color' => '#f97316',
            'whatsapp_number' => '50499991110',
            'address' => 'Boulevard Suyapa, Tegucigalpa',
            'owner_email' => 'owner-demo-ai-guide-hn@conectahn.app',
            'owner_password' => 'ChangeMe123!',
        ]);

        $officialDirectorySection = MenuSection::unguarded(fn () => MenuSection::query()->firstOrCreate([
            'tenant_id' => $officialDirectoryFixture->id,
            'name' => 'Destacados IA',
        ], [
            'is_active' => true,
            'sort_order' => 1,
        ]));

        foreach ([
            [
                'name' => 'Combo Presupuesto 120 HNL',
                'slug' => 'demo-ai-guide-hn-budget-120',
                'price' => 120,
                'description' => 'Opcion economica para presupuesto ajustado.',
            ],
            [
                'name' => 'Combo Presupuesto 350 HNL',
                'slug' => 'demo-ai-guide-hn-budget-350',
                'price' => 350,
                'description' => 'Opcion balanceada para presupuesto medio.',
            ],
            [
                'name' => 'Combo Presupuesto 950 HNL',
                'slug' => 'demo-ai-guide-hn-budget-950',
                'price' => 950,
                'description' => 'Opcion premium para presupuesto alto.',
            ],
        ] as $productFixture) {
            Product::factory()->forTenant($officialDirectoryFixture)->create([
                'name' => $productFixture['name'],
                'slug' => $productFixture['slug'],
                'description' => $productFixture['description'],
                'price' => $productFixture['price'],
                'menu_section_id' => $officialDirectorySection->id,
                'is_visible' => true,
                'is_featured' => true,
            ]);
        }

        $officialBookingFixture = $this->createTenant([
            'name' => 'Demo AI Booking HN',
            'slug' => 'demo-ai-booking-hn',
            'business_type' => 'service',
            'category_id' => $categories['belleza']->id,
            'city_id' => $cities['tegucigalpa']->id,
            'description' => 'Fixture oficial de booking para release-critical.',
            'about' => 'Tenant persistente para validar reserva publica end-to-end.',
            'slogan' => 'Agenda simple, reserva clara.',
            'primary_color' => '#111827',
            'whatsapp_number' => '50499991111',
            'address' => 'Colonia Palmira, Tegucigalpa',
            'owner_email' => 'owner-demo-ai-booking-hn@conectahn.app',
            'owner_password' => 'ChangeMe123!',
        ]);

        ServiceProvider::factory()->create([
            'tenant_id' => $officialBookingFixture->id,
            'name' => 'Laura Rivera',
            'email' => 'laura.rivera@conectahn.app',
            'is_active' => true,
        ]);

        Service::factory()->create([
            'tenant_id' => $officialBookingFixture->id,
            'name' => 'Corte y barba express',
            'description' => 'Sesion completa de corte y barba para flujo de reserva release.',
            'duration_minutes' => 45,
            'price' => 320,
            'is_active' => true,
        ]);

        $restaurant = $this->createTenant([
            'name' => 'Casa Brasa Bistro',
            'slug' => 'casa-brasa-bistro',
            'business_type' => 'food',
            'category_id' => $categories['restaurantes']->id,
            'city_id' => $cities['tegucigalpa']->id,
            'description' => 'Restaurante con menu de autor, brunch y pedidos rapidos.',
            'about' => 'Un restaurante pensado para convertir visitas en pedidos, reservas y recompras.',
            'slogan' => 'Sabores que convierten la primera visita en cliente frecuente.',
            'primary_color' => '#f97316',
            'whatsapp_number' => '50499990001',
            'address' => 'Boulevard Morazan, Tegucigalpa',
        ]);

        $starters = MenuSection::unguarded(fn () => MenuSection::query()->create([
            'tenant_id' => $restaurant->id,
            'name' => 'Platos estrella',
            'is_active' => true,
            'sort_order' => 1,
        ]));

        $drinks = MenuSection::unguarded(fn () => MenuSection::query()->create([
            'tenant_id' => $restaurant->id,
            'name' => 'Bebidas y cafes',
            'is_active' => true,
            'sort_order' => 2,
        ]));

        Product::factory()->forTenant($restaurant)->create([
            'name' => 'Brasa Signature Burger',
            'slug' => 'brasa-signature-burger',
            'description' => 'Hamburguesa premium con papas y salsa especial.',
            'price' => 289,
            'menu_section_id' => $starters->id,
            'is_visible' => true,
            'is_featured' => true,
        ]);

        Product::factory()->forTenant($restaurant)->create([
            'name' => 'Iced Latte Caribe',
            'slug' => 'iced-latte-caribe',
            'description' => 'Cafe frio con notas de vainilla y leche cremosa.',
            'price' => 95,
            'menu_section_id' => $drinks->id,
            'is_visible' => true,
        ]);

        $cellphoneStore = $this->createTenant([
            'name' => 'Movil Center HN',
            'slug' => 'movil-center-hn',
            'business_type' => 'retail',
            'category_id' => $categories['tecnologia']->id,
            'city_id' => $cities['san-pedro-sula']->id,
            'description' => 'Tienda de celulares, accesorios y soporte tecnico para equipos modernos.',
            'about' => 'Combinamos venta de smartphones, accesorios y servicio tecnico en una vitrina lista para vender.',
            'slogan' => 'Equipos, accesorios y soporte en una sola experiencia.',
            'primary_color' => '#2563eb',
            'whatsapp_number' => '50499990002',
            'address' => 'Avenida Circunvalacion, San Pedro Sula',
        ]);

        Product::factory()->forTenant($cellphoneStore)->create([
            'name' => 'iPhone 15 Pro 256GB',
            'slug' => 'iphone-15-pro-256gb',
            'description' => 'Equipo premium con garantia local y entrega inmediata.',
            'price' => 34999,
            'is_visible' => true,
            'is_featured' => true,
        ]);

        Product::factory()->forTenant($cellphoneStore)->create([
            'name' => 'Kit MagSafe Premium',
            'slug' => 'kit-magsafe-premium',
            'description' => 'Cargador, case y protector para upsell rapido.',
            'price' => 1299,
            'is_visible' => true,
        ]);

        $lawFirm = $this->createTenant([
            'name' => 'Bufete Lex HN',
            'slug' => 'bufete-lex-hn',
            'business_type' => 'service',
            'category_id' => $categories['servicios']->id,
            'city_id' => $cities['tegucigalpa']->id,
            'description' => 'Firma legal para consultas empresariales, familiares y mercantiles.',
            'about' => 'Somos una firma legal que convierte el primer contacto en una consulta mejor filtrada y mas profesional.',
            'slogan' => 'Consulta legal clara para personas y empresas.',
            'primary_color' => '#0f172a',
            'whatsapp_number' => '50499990003',
            'address' => 'Colonia Palmira, Tegucigalpa',
        ]);

        $lawProvider = ServiceProvider::factory()->create([
            'tenant_id' => $lawFirm->id,
            'name' => 'Ana Lopez',
            'email' => 'ana.lex@example.test',
            'is_active' => true,
        ]);

        Service::factory()->create([
            'tenant_id' => $lawFirm->id,
            'name' => 'Consulta legal inicial',
            'description' => 'Sesion para revisar tu caso y definir siguientes pasos.',
            'duration_minutes' => 60,
            'price' => 950,
            'is_active' => true,
        ]);

        $consultancy = $this->createTenant([
            'name' => 'Faro Growth Advisory',
            'slug' => 'faro-growth-advisory',
            'business_type' => 'service',
            'category_id' => $categories['servicios']->id,
            'city_id' => $cities['tegucigalpa']->id,
            'description' => 'Consultoria comercial y operativa para pymes que quieren crecer con mejor ejecucion.',
            'about' => 'Ayudamos a negocios a ordenar ventas, procesos y acompanamiento comercial desde una propuesta mas clara.',
            'slogan' => 'Diagnostico, estrategia y acompanamiento para crecer con mejor foco.',
            'primary_color' => '#1d4ed8',
            'whatsapp_number' => '50499990007',
            'address' => 'Lomas del Guijarro, Tegucigalpa',
        ]);

        ServiceProvider::factory()->create([
            'tenant_id' => $consultancy->id,
            'name' => 'Daniel Funez',
            'email' => 'daniel.faro@example.test',
            'is_active' => true,
        ]);

        Service::factory()->create([
            'tenant_id' => $consultancy->id,
            'name' => 'Discovery call comercial',
            'description' => 'Sesion para diagnosticar cuellos de botella y definir una hoja de ruta.',
            'duration_minutes' => 50,
            'price' => 1200,
            'is_active' => true,
        ]);

        $clinic = $this->createTenant([
            'name' => 'Clinica Salud Vital',
            'slug' => 'clinica-salud-vital',
            'business_type' => 'service',
            'category_id' => $categories['salud']->id,
            'city_id' => $cities['la-ceiba']->id,
            'description' => 'Clinica integral con evaluaciones, programas de salud y agenda visible.',
            'about' => 'Centro de salud con enfoque en confianza, agenda y orientacion rapida para pacientes.',
            'slogan' => 'Reserva evaluaciones con una experiencia clara y confiable.',
            'primary_color' => '#059669',
            'whatsapp_number' => '50499990004',
            'address' => 'Avenida San Isidro, La Ceiba',
        ]);

        ServiceProvider::factory()->create([
            'tenant_id' => $clinic->id,
            'name' => 'Dra. Sofia Pineda',
            'email' => 'sofia.salud@example.test',
            'is_active' => true,
        ]);

        Service::factory()->create([
            'tenant_id' => $clinic->id,
            'name' => 'Evaluacion general',
            'description' => 'Consulta inicial para diagnostico y seguimiento.',
            'duration_minutes' => 45,
            'price' => 700,
            'is_active' => true,
        ]);

        $barbershop = $this->createTenant([
            'name' => 'Barberia Norte Pro',
            'slug' => 'barberia-norte-pro',
            'business_type' => 'service',
            'category_id' => $categories['belleza']->id,
            'city_id' => $cities['san-pedro-sula']->id,
            'description' => 'Barberia con agenda, grooming y promociones semanales.',
            'about' => 'Una barberia que convierte estilo, agenda y atencion rapida en una experiencia premium.',
            'slogan' => 'Cortes, barba y grooming con presencia profesional.',
            'primary_color' => '#111827',
            'whatsapp_number' => '50499990005',
            'address' => 'Barrio Rio de Piedras, San Pedro Sula',
        ]);

        ServiceProvider::factory()->create([
            'tenant_id' => $barbershop->id,
            'name' => 'Marco Diaz',
            'email' => 'marco.barber@example.test',
            'is_active' => true,
        ]);

        Service::factory()->create([
            'tenant_id' => $barbershop->id,
            'name' => 'Corte premium con barba',
            'description' => 'Sesion completa de corte, perfilado y grooming.',
            'duration_minutes' => 50,
            'price' => 320,
            'is_active' => true,
        ]);

        $workshop = $this->createTenant([
            'name' => 'Taller Motor Pro',
            'slug' => 'taller-motor-pro',
            'business_type' => 'hybrid',
            'category_id' => $categories['automotriz']->id,
            'city_id' => $cities['tegucigalpa']->id,
            'description' => 'Taller automotriz con diagnostico, servicios y repuestos visibles.',
            'about' => 'Negocio hibrido para diagnostico, mantenimiento y venta de repuestos.',
            'slogan' => 'Diagnostico tecnico con repuestos y contacto directo.',
            'primary_color' => '#dc2626',
            'whatsapp_number' => '50499990006',
            'address' => 'Anillo periferico, Tegucigalpa',
        ]);

        ServiceProvider::factory()->create([
            'tenant_id' => $workshop->id,
            'name' => 'Luis Mejia',
            'email' => 'luis.motor@example.test',
            'is_active' => true,
        ]);

        Service::factory()->create([
            'tenant_id' => $workshop->id,
            'name' => 'Diagnostico computarizado',
            'description' => 'Revision inicial con hallazgos y propuesta de trabajo.',
            'duration_minutes' => 40,
            'price' => 450,
            'is_active' => true,
        ]);

        Product::factory()->forTenant($workshop)->create([
            'name' => 'Filtro premium para sedan',
            'slug' => 'filtro-premium-sedan',
            'description' => 'Repuesto listo para taller y venta directa.',
            'price' => 390,
            'is_visible' => true,
        ]);

        $casualRestaurant = $this->createTenant([
            'name' => 'Patio Casual Bistro',
            'slug' => 'patio-casual-bistro',
            'business_type' => 'food',
            'category_id' => $categories['restaurantes']->id,
            'city_id' => $cities['san-pedro-sula']->id,
            'description' => 'Restaurante casual con burgers, lunch specials y combos de alto ticket.',
            'about' => 'Un bistro casual pensado para mover platos estrella, combos y recompra semanal.',
            'slogan' => 'Combos, antojo y pedidos rapidos para volver siempre.',
            'primary_color' => '#ea580c',
            'whatsapp_number' => '50499990008',
            'address' => 'Boulevard Mackay, San Pedro Sula',
        ]);

        $casualMains = MenuSection::unguarded(fn () => MenuSection::query()->create([
            'tenant_id' => $casualRestaurant->id,
            'name' => 'Combos casuales',
            'is_active' => true,
            'sort_order' => 1,
        ]));

        Product::factory()->forTenant($casualRestaurant)->create([
            'name' => 'Burger combo de la casa',
            'slug' => 'burger-combo-de-la-casa',
            'description' => 'Burger premium con papas y bebida para subir ticket desde la primera orden.',
            'price' => 245,
            'menu_section_id' => $casualMains->id,
            'is_visible' => true,
            'is_featured' => true,
        ]);

        $coffeeShop = $this->createTenant([
            'name' => 'Alto Brew Cafeteria',
            'slug' => 'alto-brew-cafeteria',
            'business_type' => 'food',
            'category_id' => $categories['restaurantes']->id,
            'city_id' => $cities['tegucigalpa']->id,
            'description' => 'Cafeteria de especialidad con espresso, brunch y pickup rapido.',
            'about' => 'Una cafeteria que mezcla marca, comunidad y una carta pensada para la rutina diaria.',
            'slogan' => 'Cafe de especialidad, brunch y pedidos ligeros sin filas.',
            'primary_color' => '#7c3aed',
            'whatsapp_number' => '50499990009',
            'address' => 'Colonia Florencia, Tegucigalpa',
        ]);

        $coffeeMenu = MenuSection::unguarded(fn () => MenuSection::query()->create([
            'tenant_id' => $coffeeShop->id,
            'name' => 'Especialidades y brunch',
            'is_active' => true,
            'sort_order' => 1,
        ]));

        Product::factory()->forTenant($coffeeShop)->create([
            'name' => 'Cold brew de temporada',
            'slug' => 'cold-brew-de-temporada',
            'description' => 'Bebida firma para manana, tarde o pickup express.',
            'price' => 110,
            'menu_section_id' => $coffeeMenu->id,
            'is_visible' => true,
            'is_featured' => true,
        ]);

        $premiumPhoneStore = $this->createTenant([
            'name' => 'Titan Flagship Mobile',
            'slug' => 'titan-flagship-mobile',
            'business_type' => 'retail',
            'category_id' => $categories['tecnologia']->id,
            'city_id' => $cities['tegucigalpa']->id,
            'description' => 'Tienda de celulares premium con flagships, bundles y soporte posventa.',
            'about' => 'Retail de tecnologia para equipos de gama alta, accesorios premium y una experiencia de compra mas confiable.',
            'slogan' => 'Flagships, bundles y soporte que justifican el ticket.',
            'primary_color' => '#1d4ed8',
            'whatsapp_number' => '50499990010',
            'address' => 'Boulevard Juan Pablo II, Tegucigalpa',
        ]);

        Product::factory()->forTenant($premiumPhoneStore)->create([
            'name' => 'Galaxy Ultra flagship bundle',
            'slug' => 'galaxy-ultra-flagship-bundle',
            'description' => 'Equipo de gama alta con bundle MagSafe y garantia local.',
            'price' => 38999,
            'is_visible' => true,
            'is_featured' => true,
        ]);

        $hardwareStore = $this->createTenant([
            'name' => 'Ferreteria FerreMax HN',
            'slug' => 'ferreteria-ferremax-hn',
            'business_type' => 'retail',
            'category_id' => $categories['hogar']->id,
            'city_id' => $cities['la-ceiba']->id,
            'description' => 'Ferreteria con herramientas, materiales y cotizacion rapida para proyectos y reparaciones.',
            'about' => 'Una ferreteria pensada para ventas por proyecto, mantenimiento y urgencias del dia a dia.',
            'slogan' => 'Herramientas, materiales y respuesta comercial sin vueltas.',
            'primary_color' => '#f59e0b',
            'whatsapp_number' => '50499990011',
            'address' => 'Avenida 14 de Julio, La Ceiba',
        ]);

        Product::factory()->forTenant($hardwareStore)->create([
            'name' => 'Kit de herramientas para obra',
            'slug' => 'kit-de-herramientas-para-obra',
            'description' => 'Bundle de herramientas basicas para mantenimiento y construccion ligera.',
            'price' => 1890,
            'is_visible' => true,
            'is_featured' => true,
        ]);

        $dentalClinic = $this->createTenant([
            'name' => 'Clinica Dental Sonrisa',
            'slug' => 'clinica-dental-sonrisa',
            'business_type' => 'service',
            'category_id' => $categories['salud']->id,
            'city_id' => $cities['tegucigalpa']->id,
            'description' => 'Clinica dental con valoracion, ortodoncia y tratamientos visibles desde la portada.',
            'about' => 'Una clinica dental que orienta mejor al paciente y vuelve la agenda un proceso mas claro.',
            'slogan' => 'Valoraciones claras para una sonrisa mejor cuidada.',
            'primary_color' => '#14b8a6',
            'whatsapp_number' => '50499990012',
            'address' => 'Colonia Tepeyac, Tegucigalpa',
        ]);

        ServiceProvider::factory()->create([
            'tenant_id' => $dentalClinic->id,
            'name' => 'Dra. Mariana Rosales',
            'email' => 'mariana.dental@example.test',
            'is_active' => true,
        ]);

        Service::factory()->create([
            'tenant_id' => $dentalClinic->id,
            'name' => 'Valoracion odontologica',
            'description' => 'Revision inicial para diagnostico, presupuesto y siguiente paso clinico.',
            'duration_minutes' => 45,
            'price' => 850,
            'is_active' => true,
        ]);

        $corporateLawFirm = $this->createTenant([
            'name' => 'Lex Corporativo HN',
            'slug' => 'lex-corporativo-hn',
            'business_type' => 'service',
            'category_id' => $categories['servicios']->id,
            'city_id' => $cities['san-pedro-sula']->id,
            'description' => 'Bufete corporativo para compliance, contratos empresariales y mercantil.',
            'about' => 'Firma legal enfocada en clientes empresa, con intake mas serio y servicios corporativos bien aterrizados.',
            'slogan' => 'Asesoria corporativa con criterio de negocio y respuesta clara.',
            'primary_color' => '#0f172a',
            'whatsapp_number' => '50499990013',
            'address' => 'Colonia Trejo, San Pedro Sula',
        ]);

        ServiceProvider::factory()->create([
            'tenant_id' => $corporateLawFirm->id,
            'name' => 'Lic. Andrea Carias',
            'email' => 'andrea.corporativo@example.test',
            'is_active' => true,
        ]);

        Service::factory()->create([
            'tenant_id' => $corporateLawFirm->id,
            'name' => 'Sesion de diagnostico corporativo',
            'description' => 'Revision inicial para compliance, contratos y estructura legal de la empresa.',
            'duration_minutes' => 60,
            'price' => 1500,
            'is_active' => true,
        ]);

        $financialConsultancy = $this->createTenant([
            'name' => 'Faro Finanzas Advisory',
            'slug' => 'faro-finanzas-advisory',
            'business_type' => 'service',
            'category_id' => $categories['servicios']->id,
            'city_id' => $cities['tegucigalpa']->id,
            'description' => 'Consultoria financiera para flujo de caja, rentabilidad y acompanamiento ejecutivo.',
            'about' => 'Ayudamos a pymes a entender su caja, tomar decisiones mejores y ordenar su seguimiento financiero.',
            'slogan' => 'Flujo de caja, rentabilidad y decisiones con mas claridad.',
            'primary_color' => '#1e40af',
            'whatsapp_number' => '50499990014',
            'address' => 'Lomas del Mayab, Tegucigalpa',
        ]);

        ServiceProvider::factory()->create([
            'tenant_id' => $financialConsultancy->id,
            'name' => 'Rene Ponce',
            'email' => 'rene.finanzas@example.test',
            'is_active' => true,
        ]);

        Service::factory()->create([
            'tenant_id' => $financialConsultancy->id,
            'name' => 'Diagnostico de flujo de caja',
            'description' => 'Sesion para revisar caja, margen y prioridades financieras de la empresa.',
            'duration_minutes' => 55,
            'price' => 1300,
            'is_active' => true,
        ]);

        $premiumBarbershop = $this->createTenant([
            'name' => 'District Barber Lounge',
            'slug' => 'district-barber-lounge',
            'business_type' => 'service',
            'category_id' => $categories['belleza']->id,
            'city_id' => $cities['san-pedro-sula']->id,
            'description' => 'Barber lounge premium con grooming, slots premium y atencion aspiracional.',
            'about' => 'Una barber premium donde la marca, la agenda y el ticket viven como una sola experiencia.',
            'slogan' => 'Grooming premium con estilo, agenda y presencia de marca.',
            'primary_color' => '#111827',
            'whatsapp_number' => '50499990015',
            'address' => 'Colonia Moderna, San Pedro Sula',
        ]);

        ServiceProvider::factory()->create([
            'tenant_id' => $premiumBarbershop->id,
            'name' => 'Jorge Galeas',
            'email' => 'jorge.lounge@example.test',
            'is_active' => true,
        ]);

        Service::factory()->create([
            'tenant_id' => $premiumBarbershop->id,
            'name' => 'Ritual premium de barberia',
            'description' => 'Corte, barba, hot towel y grooming completo en una sola sesion.',
            'duration_minutes' => 60,
            'price' => 450,
            'is_active' => true,
        ]);

        $spaStudio = $this->createTenant([
            'name' => 'Aura Spa Studio',
            'slug' => 'aura-spa-studio',
            'business_type' => 'service',
            'category_id' => $categories['belleza']->id,
            'city_id' => $cities['la-ceiba']->id,
            'description' => 'Spa studio con masajes, faciales y paquetes de bienestar premium.',
            'about' => 'Un estudio de bienestar que combina rituales, agenda y una narrativa mas sensorial para vender mejor.',
            'slogan' => 'Bienestar premium para volver una pausa en rutina.',
            'primary_color' => '#a855f7',
            'whatsapp_number' => '50499990016',
            'address' => 'Zona Viva, La Ceiba',
        ]);

        ServiceProvider::factory()->create([
            'tenant_id' => $spaStudio->id,
            'name' => 'Valeria Soto',
            'email' => 'valeria.spa@example.test',
            'is_active' => true,
        ]);

        Service::factory()->create([
            'tenant_id' => $spaStudio->id,
            'name' => 'Ritual facial y relajacion',
            'description' => 'Sesion de bienestar con facial, masaje corto y cierre premium.',
            'duration_minutes' => 70,
            'price' => 980,
            'is_active' => true,
        ]);

        $autoPartsStore = $this->createTenant([
            'name' => 'Repuestos Turbo Center',
            'slug' => 'repuestos-turbo-center',
            'business_type' => 'hybrid',
            'category_id' => $categories['automotriz']->id,
            'city_id' => $cities['tegucigalpa']->id,
            'description' => 'Tienda de repuestos con autopartes, diagnostico e instalacion para vehiculos livianos.',
            'about' => 'Negocio hibrido para vender piezas, orientar compatibilidad y resolver soporte tecnico desde un solo frente.',
            'slogan' => 'Autopartes, diagnostico y soluciones en una sola ruta.',
            'primary_color' => '#dc2626',
            'whatsapp_number' => '50499990017',
            'address' => 'Salida a Olancho, Tegucigalpa',
        ]);

        ServiceProvider::factory()->create([
            'tenant_id' => $autoPartsStore->id,
            'name' => 'Carlos Erazo',
            'email' => 'carlos.repuestos@example.test',
            'is_active' => true,
        ]);

        Service::factory()->create([
            'tenant_id' => $autoPartsStore->id,
            'name' => 'Revision de compatibilidad y montaje',
            'description' => 'Chequeo inicial para validar pieza, compatibilidad y propuesta de instalacion.',
            'duration_minutes' => 35,
            'price' => 420,
            'is_active' => true,
        ]);

        Product::factory()->forTenant($autoPartsStore)->create([
            'name' => 'Kit premium de frenos delanteros',
            'slug' => 'kit-premium-de-frenos-delanteros',
            'description' => 'Autopartes para mantenimiento preventivo con alto movimiento.',
            'price' => 2450,
            'is_visible' => true,
            'is_featured' => true,
        ]);

        foreach ([
            $officialDirectoryFixture,
            $officialBookingFixture,
            $restaurant,
            $cellphoneStore,
            $lawFirm,
            $consultancy,
            $clinic,
            $barbershop,
            $workshop,
            $casualRestaurant,
            $coffeeShop,
            $premiumPhoneStore,
            $hardwareStore,
            $dentalClinic,
            $corporateLawFirm,
            $financialConsultancy,
            $premiumBarbershop,
            $spaStudio,
            $autoPartsStore,
        ] as $tenant) {
            $this->seedOpeningHours($tenant);
        }

        app(TenantContext::class)->reset();

        $this->command?->info('E2E storefront fixtures seeded.');
    }

    private function initializeConsoleTenantContext(): void
    {
        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);

        if ($tenantContext->isInitialized() && ! $tenantContext->isRoot()) {
            $tenantContext->reset();
        }

        if (! $tenantContext->isRoot()) {
            $tenantContext->setAsSystemRoot(isConsoleOrTesting: true);
        }
    }

    private function createTenant(array $attributes): Tenant
    {
        $ownerEmail = (string) ($attributes['owner_email'] ?? Str::slug($attributes['slug']).'@example.test');
        $ownerPassword = (string) ($attributes['owner_password'] ?? 'password');

        $owner = User::query()->updateOrCreate(
            ['email' => $ownerEmail],
            [
                'name' => $attributes['name'].' Owner',
                'password' => Hash::make($ownerPassword),
                'email_verified_at' => now(),
                'role' => \App\Domain\Identity\Enums\UserRole::TENANT_OWNER->value,
            ],
        );

        $tenant = Tenant::withoutEvents(fn () => Tenant::query()->create([
            'user_id' => $owner->id,
            'name' => $attributes['name'],
            'slug' => $attributes['slug'],
            'description' => $attributes['description'],
            'about' => $attributes['about'],
            'slogan' => $attributes['slogan'],
            'address' => $attributes['address'],
            'city_id' => $attributes['city_id'],
            'category_id' => $attributes['category_id'],
            'whatsapp_number' => $attributes['whatsapp_number'],
            'phone' => $attributes['whatsapp_number'],
            'primary_color' => $attributes['primary_color'],
            'brand_color' => $attributes['primary_color'],
            'business_type' => $attributes['business_type'],
            'status' => 'approved',
            'is_active' => true,
            'plan_type' => 'pro',
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addMonths(6),
            'product_limit' => 500,
            'verification_status' => Tenant::VERIFICATION_STATUS_VERIFIED,
            'verified_at' => now(),
        ]));

        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        return $tenant;
    }

    private function createCity(string $departmentName, string $municipalityName, string $cityName, float $lat, float $lng): City
    {
        $department = Department::query()->firstOrCreate(
            ['slug' => Str::slug($departmentName)],
            ['name' => $departmentName],
        );

        $municipality = Municipality::query()->firstOrCreate(
            ['slug' => Str::slug($municipalityName)],
            [
                'department_id' => $department->id,
                'name' => $municipalityName,
            ],
        );

        return City::query()->firstOrCreate(
            ['slug' => Str::slug($cityName)],
            [
                'municipality_id' => $municipality->id,
                'name' => $cityName,
                'coordinates' => ['lat' => $lat, 'lng' => $lng],
                'is_active' => true,
                'is_featured' => true,
            ],
        );
    }

    private function seedOpeningHours(Tenant $tenant): void
    {
        foreach (range(0, 6) as $dayOfWeek) {
            OpeningHour::unguarded(fn () => OpeningHour::query()->create([
                'tenant_id' => $tenant->id,
                'day_of_week' => $dayOfWeek,
                'opening_time' => '09:00:00',
                'closing_time' => '18:00:00',
            ]));
        }
    }
}
