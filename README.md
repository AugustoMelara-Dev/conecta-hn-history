<p align="center">
  <img src="public/images/logo.png" alt="Conecta HN Logo" width="200"/>
</p>

<h1 align="center">🇭🇳 Conecta HN</h1>
<h3 align="center">La Plataforma Definitiva de Comercio Local en Honduras</h3>

<p align="center">
  <strong>SaaS Multi-Tenant · Mobile-First · Growth-Optimized</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11"/>
  <img src="https://img.shields.io/badge/Livewire-3.x-FB70A9?style=for-the-badge&logo=livewire&logoColor=white" alt="Livewire 3"/>
  <img src="https://img.shields.io/badge/FilamentPHP-3.x-FFAE00?style=for-the-badge&logo=laravel&logoColor=white" alt="Filament 3"/>
  <img src="https://img.shields.io/badge/Tailwind-3.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="Tailwind CSS"/>
  <img src="https://img.shields.io/badge/Alpine.js-3.x-8BC0D0?style=for-the-badge&logo=alpine.js&logoColor=white" alt="Alpine.js"/>
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL"/>
</p>

<p align="center">
  <a href="#características-principales">Características</a> •
  <a href="#stack-tecnológico">Stack</a> •
  <a href="#ingeniería-de-características">Ingeniería</a> •
  <a href="#instalación">Instalación</a> •
  <a href="#roadmap">Roadmap</a>
</p>

---

## 📋 Descripción del Producto

**Conecta HN** no es un simple directorio de negocios. Es una **plataforma SaaS Multi-Tenant** diseñada específicamente para el ecosistema comercial de Honduras, donde el **90% del tráfico web proviene de dispositivos móviles** y donde WhatsApp es el canal de comunicación empresarial dominante. La plataforma resuelve un problema crítico: la **fragmentación del comercio local**, donde miles de pequeños negocios operan de manera informal a través de redes sociales sin presencia web estructurada, perdiendo oportunidades de descubrimiento, credibilidad y conversión.

Cada negocio registrado obtiene su propio **Micrositio Optimizado** (`/nombre-negocio`) con SEO avanzado, catálogo de productos, horarios de atención, ubicación GPS y un sistema de conversión integrado hacia WhatsApp. La arquitectura **Mobile-First** garantiza tiempos de carga sub-segundo incluso en redes 3G, mientras que el sistema de **Growth Hacking integrado** proporciona a los dueños de negocios métricas en tiempo real sobre visitas, clics y tasas de conversión, incentivando la retención y el upgrade a planes premium.

---

## 🔧 Stack Tecnológico

### Backend: Laravel 11
Laravel actúa como el **orquestador central de lógica de negocio**, manejando autenticación multi-tenant, validación de límites de plan, y una capa de servicios desacoplada. Implementamos:
- **Route Model Binding** con slugs para URLs SEO-friendly
- **Eloquent Relationships** polimórficas para flexibilidad
- **Atomic Database Operations** para contadores de alta concurrencia
- **Background Jobs** para procesamiento asíncrono de analítica

### Frontend Dinámico: Livewire 3 + Alpine.js
Los componentes Livewire operan en **modo SPA** mediante `wire:navigate`, eliminando recargas de página completas. Esto crea una experiencia nativa de aplicación móvil:
- **Infinite Scroll** con `x-intersect` para lazy loading de listados
- **Skeleton Loaders** que mejoran la percepción de velocidad
- **Reactive State Management** sin JavaScript complejo
- **Form Wizards** multi-paso para registro de negocios

### Panel Administrativo: FilamentPHP 3
Implementamos **dos paneles completamente separados**:
- **Super Admin (`/admin`)**: Gestión de tenants, moderación, pagos, analítica global
- **Tenant App (`/app`)**: Dashboard personalizado por negocio con métricas de conversión

Cada panel tiene su propio `Provider`, middleware, tema de colores y widgets contextuales.

### Estilos: Tailwind CSS 3
Utilizamos una **paleta semántica** con tokens de color consistentes y un sistema de diseño que prioriza:
- **Glassmorphism** para elementos flotantes (sticky bars, navigation)
- **Micro-animaciones** para feedback visual (hover states, transitions)
- **Dark Mode** nativo en el panel administrativo
- **Responsive Breakpoints** optimizados para dispositivos hondureños comunes

---

## ⚡ Ingeniería de Características

### 🔬 Atomic Analytics Engine

El sistema de analítica está diseñado para **alta concurrencia sin bloqueos**. Cuando un usuario visita un micrositio:

```php
// ShowTenant.php - Atomic increment sin row locking
Tenant::where('id', $this->tenant->id)->increment('views');
```

Para conversiones (clics en WhatsApp, llamadas, navegación GPS), implementamos un **Middleware de Tracking**:

```php
// InteractionController.php - Redirect pattern
public function whatsapp(Request $request, Tenant $tenant): RedirectResponse
{
    // 1. Registrar interacción en DB
    Interaction::create([
        'tenant_id' => $tenant->id,
        'type' => Interaction::TYPE_WHATSAPP_CLICK,
        'ip_address' => $request->ip(),
        'metadata' => ['message' => $request->input('text')],
    ]);
    
    // 2. Redirigir a WhatsApp (imperceptible para usuario)
    return redirect()->away("https://wa.me/{$phone}?text={$message}");
}
```

Este patrón **captura el 100% de las conversiones** sin afectar la experiencia del usuario.

### 📊 Growth Hacking Dashboard (Bento Grid)

El dashboard del tenant implementa **psicología de ventas** a nivel de UI:

| Plan | Comportamiento Visual |
|------|----------------------|
| **FREE** | Gradiente azul-púrpura, mensaje de urgencia ("Estás perdiendo clientes..."), CTA prominente |
| **PRO** | Gradiente oscuro elegante, badges de verificación, todas las métricas desbloqueadas |

```php
// TenantStatsWidget.php - Lógica de diferenciación
$this->isPro = (bool) $tenant->is_pro;
$this->planName = $this->isPro ? 'Plan PRO' : 'Plan Gratuito';
$this->productLimit = $settings->free_plan_product_limit ?? 10;
```

El widget muestra 4 tarjetas clave: **Visitas Totales**, **Clics WhatsApp**, **Productos Activos**, y **Estado de Suscripción**.

### 🔀 Polymorphic Tenant System

El componente `ShowTenant.php` adapta dinámicamente la UI según el plan:

```php
// Secciones condicionales en la vista
@if($tenant->is_pro)
    <x-structured-hours :schedule="$schedule" />
    <x-embedded-map :latitude="$lat" :longitude="$lng" />
    <x-product-gallery :products="$products" />
@else
    <x-collapsed-hours />
    <x-upgrade-banner />
@endif
```

Además, se implementa **seguridad a nivel de modelo**:
- Tenants con `is_active = false` retornan 404
- `previewMode` permite a admins ver perfiles no publicados

### 🎭 Performance Percibida (Perceived Performance)

Implementamos **Skeleton Loaders** en el directorio:

```html
<!-- Skeleton mientras carga el contenido real -->
<div wire:loading class="animate-pulse">
    <div class="h-48 bg-gray-200 rounded-xl"></div>
    <div class="h-4 bg-gray-200 rounded mt-3 w-3/4"></div>
</div>
```

Combinado con **Lazy Loading** de imágenes y **x-intersect** para infinite scroll.

---

## 🗄️ Estructura de Base de Datos

### Entidades Principales

| Modelo | Propósito |
|--------|-----------|
| `Tenant` | Núcleo del sistema. Representa un negocio con perfil, plan, coordenadas GPS |
| `Product` | Catálogo de productos/servicios con precios, imágenes, blurhash |
| `MenuSection` | Organización jerárquica del catálogo (Desayunos, Almuerzos, etc.) |
| `Interaction` | Analítica granular: visitas, clics WhatsApp, llamadas, navegación |
| `Payment` | Flujo de pagos manuales con estados (pending, approved, rejected) |
| `City` / `Category` | Taxonomía geográfica y temática para filtrado y SEO |

### Migraciones Consolidadas

Hemos refactorizado **+20 migraciones dispersas** en un esquema semántico:

```
2025_01_01_000001_create_location_tables.php    # Ciudades, municipios
2025_01_01_000002_create_categories_table.php   # Categorías con estilos
2025_01_01_000003_create_tenants_table.php      # Negocios multi-tenant
2025_01_01_000004_create_products_tables.php    # Catálogo y variantes
2025_01_01_000005_create_social_tables.php      # Follows, reviews
2025_01_01_000006_create_marketing_tables.php   # Campaigns, leads
2025_01_01_000007_create_business_tables.php    # Payments, interactions
```

---

## 🚀 Instalación

### Requisitos Previos
- PHP 8.2+
- Composer 2.x
- Node.js 18+ & npm
- MySQL 8.0+

### Pasos de Instalación

```bash
# 1. Clonar repositorio
git clone https://github.com/AugustoMelara-Dev/conectahn.git
cd conectahn

# 2. Instalar dependencias PHP
composer install

# 3. Instalar dependencias Node y compilar assets
npm install && npm run build

# 4. Configurar entorno
cp .env.example .env
php artisan key:generate

# 5. Configurar base de datos en .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=conecta_hn
# DB_USERNAME=root
# DB_PASSWORD=

# 6. Ejecutar migraciones y seeders
# ⚠️ Los seeders crean: Admin user, roles/permisos, ciudades, categorías, y negocios de prueba
php artisan migrate --seed

# 7. Crear link simbólico para storage
php artisan storage:link

# 8. Iniciar servidor de desarrollo
php artisan serve
```

### Acceso al Sistema

| URL | Descripción | Credenciales |
|-----|-------------|--------------|
| `http://localhost:8000` | Landing Page Pública | - |
| `http://localhost:8000/directorio` | Directorio de Negocios | - |
| `http://localhost:8000/admin` | Super Admin Panel | Ver seeder |
| `http://localhost:8000/app` | Tenant Dashboard | Ver seeder |

---

## 🗺️ Roadmap

### Fase 2: Monetización Avanzada
- [ ] **Integración de Pagos en Línea** (Stripe/PayPal para Honduras)
- [ ] **Planes de Suscripción Automatizados** con renovación automática
- [ ] **Sistema de Cupones y Descuentos** para promociones

### Fase 3: Engagement & Retención
- [ ] **Notificaciones Push vía WhatsApp** (API oficial)
- [ ] **Reportes Semanales Automáticos** por correo electrónico
- [ ] **Sistema de Lealtad** (puntos por compra para compradores)

### Fase 4: Expansión
- [ ] **Aplicación Móvil PWA** con notificaciones push
- [ ] **Marketplace Integrado** con carrito de compras
- [ ] **Soporte Multi-país** (Guatemala, El Salvador)

---

## 👥 Contribución

1. Fork el repositorio
2. Crea tu feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push al branch (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

---

## 📄 Licencia

Este proyecto está bajo la licencia MIT. Ver el archivo `LICENSE` para más detalles.

---

<p align="center">
  <strong>Construido con ❤️ para el comercio hondureño</strong><br>
  <sub>© 2024 Conecta HN. Todos los derechos reservados.</sub>
</p>