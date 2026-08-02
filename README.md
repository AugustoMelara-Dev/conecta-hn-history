# Dovra AI — Global Conversational Business OS

Dovra AI es la identidad publica canonica de este repositorio. El sistema es un modular monolith multi-tenant con capacidades de catalogos, pedidos, citas, marketing, operaciones, inteligencia y automatizacion gobernada. El repositorio conserva superficies y artefactos historicos de Conecta HN y `Vito Business OS` como evidencia de migracion y compatibilidad; esos nombres no son la identidad publica actual.

Este README documenta el estado actual del proyecto al 2026-05-04.

> Nota de marca y alcance
> Migracion fechada: Conecta HN → Dovra AI. La linea historica se conserva para compatibilidad, auditoria y rollback; la identidad de producto que debe usarse en nuevas superficies publicas es Dovra AI.

## Product truth and claims boundary

- `verified_repository_capability`: solo lo que tiene una ruta de codigo y evidencia local reproducible en este checkout.
- `implementation_target`: contratos, interfaces o slices descritos por el plan que aun requieren validacion adicional.
- `external_dependency`: secretos, proveedores live, DNS, pagos, despliegue, restore administrado, legal y compliance.
- `unsupported_claim`: no se infieren produccion publica, certificaciones, compliance, traccion, ingresos, investment readiness ni disponibilidad live a partir de nombres, tests o documentos.

La certificacion exacta se limita al artefacto que inspecciona `scripts/certify-dovra-launch.php`. Un arbol dirty puede producir un inventario, pero no es un artefacto inmutable certificable ni evidencia de disponibilidad publica.

## Operacion y auditoria (No-MVP)

- [Architecture Audit Pack 2026-04](docs/architecture/AUDIT_PACK_NO_MVP_2026-04.md)
- [Deploy topology Vercel + Railway](docs/architecture/DEPLOY_TOPOLOGY_VERCEL_RAILWAY.md)
- [Guide RAG readiness blueprint](docs/architecture/GUIDE_RAG_READINESS_BLUEPRINT.md)
- [Credential incident closure checklist](docs/security/CREDENTIAL_INCIDENT_CLOSURE.md)
- [Git Ops rapido](docs/git-ops.md)

## Operacion global por lotes (subagentes)

Estandar operativo activo para auditoria y mejora continua:

- Auditoria primero, correccion despues.
- Lotes largos de 8-12h con checkpoints cada 2-3h.
- Rama y PR separada por frente (ownership disjunto).
- Cierre por frente solo con: tests pasando + evidencia reproducible + PR lista.
- Si hay bloqueo externo (infra/credenciales), estado `partial` con operador-step explicito.

Frentes de trabajo actuales:

- Frente A: negocio + superadmin.
- Frente B: CRM operativo parcial + campanas + inbox.
- Frente C: billing + settings.
- Frente D: calidad transversal (regresion, estabilidad de suite, evidencia de riesgo).

Merge order recomendado para lotes de producto critico:

1. A
2. D
3. B
4. C

Nota de repositorio:

- El remoto canonico de organizacion para integracion es `VitoCode-Technologies/Vito-Business-OS`.
- La rama principal objetivo de integracion es `main`.

## Estado operacional actual (2026-04-26)

- Lote 002 (frentes A/B/C/D) ya fue integrado en `main` mediante squash merge por frente.
- Las protecciones de `main` se mantienen activas: ruleset + branch protection.
- Modo temporal por billing de GitHub Actions: sin `required status check` obligatorio en `main` para evitar bloqueo operativo por falta de credito.
- Politica CI temporal (ultra-ahorro): workflows en ejecucion manual (`workflow_dispatch`) para controlar costo mientras se normaliza billing.
- Se ejecuto limpieza de ramas remotas legacy para reducir ruido operativo.
- Inventario remoto intencional actual:
  - `main`

## Que contiene este repo

Hoy el proyecto ya incluye:

- Sitio publico en `Inertia + React` para landing, discovery, directorio y conversion.
- Micrositios de negocio con catalogo, SEO, tracking y checkout.
- Registro publico de negocios con chequeo de slug y flujo de pago pendiente.
- Autenticacion web con Fortify y autenticacion API con OTP, social login y Sanctum.
- Cuenta web con registro, login, reset de password, verificacion de email, perfil y favoritos.
- App operativa del negocio en `workspace/*`.
- Panel Filament del tenant en `/app`.
- Panel Filament super admin en `/admin`.
- Reservas y citas con servicios, proveedores, slots, confirmacion y recordatorios.
- Cupones, campanas, anuncios, followers, loyalty, notificaciones y reportes exportables.
- Tracking auxiliar para WhatsApp, llamadas, mapas, geolocalizacion y analitica publica.
- AI para optimizar perfiles con control de presupuesto y auditoria de consumo (sujeto a configuracion de entorno e integraciones activas).
- Realtime, broadcast y PWA con Reverb, Echo, Web Push y `vite-plugin-pwa`.
- Contratos con JSON Schema, Swagger UI, tipos TypeScript generados y proto gRPC.
- Operacion endurecida con outbox transaccional, idempotencia, signed downloads, uploads firmados, response cache, health checks, workers y scheduler.
- Soporte operativo con shadow login / impersonation y tenancy por path en local mas soporte por subdominio en configuracion.

## Alcance CRM actual (honesto)

El alcance CRM activo hoy es operativo parcial:

- perfiles de clientes (`CustomerProfile`)
- inbox y conversaciones operativas (`CustomerInbox`)
- notas y tareas de seguimiento (`CustomerNote`, `CustomerTask`)
- oportunidades comerciales basicas (`CrmDeal`) con pipeline/etapa para tenants habilitados
- touchpoints y campanas donde la configuracion del tenant lo habilita

No debe interpretarse como CRM enterprise completo. Aunque existen pipelines/etapas/deals basicos para tenants habilitados, todavia faltan capas clave como permisos CRM granulares por accion, import/export robusto, automatizaciones maduras y reporting comercial avanzado.

## Estado de readiness comercial

- Este repositorio contiene capacidades implementadas y contratos operativos en varias superficies; la disponibilidad en produccion publica no esta establecida por este README.
- El estado de lanzamiento depende de gates operativos (smokes desplegados, secretos, colas, storage, observabilidad y checks de CI), varios de los cuales son externos o estan pendientes.
- Las capacidades de Growth, AI y RAG requieren datos, jobs y configuracion de integraciones (por ejemplo keys y vector store) para operar de forma verificable en cada entorno.

## Stack actual

| Capa | Tecnologias |
| --- | --- |
| Backend | PHP 8.2, Laravel 12, Sanctum, Fortify |
| Frontend publico | Inertia.js 2, React 18, TypeScript, Vite 5, Tailwind CSS 3 |
| Paneles | Filament 4.2, Livewire 3 |
| UI y estado | Zustand, Recharts, Radix UI, Headless UI, Framer Motion |
| Realtime y PWA | Laravel Reverb, Laravel Echo, Pusher JS, vite-plugin-pwa, Web Push |
| Datos | SQLite para local rapido, MySQL o MariaDB para entornos persistentes |
| Storage y media | Spatie Medialibrary, storage local, signed downloads, pre-signed uploads S3 |
| Infra recomendada | Redis, Supervisor, Nginx |
| Observabilidad | Laravel Pail, Telescope, Spatie Health, Spatie Activitylog |
| Seguridad | Spatie Permission, rate limiting contextual, idempotency middleware, signed URLs |

## Superficies activas del producto

### Publico

| Ruta | Proposito |
| --- | --- |
| `/` | Landing publica |
| `/explore` | Discovery y exploracion |
| `/directorio` | Directorio de negocios |
| `/ciudad/{slug}` | Landing SEO por ciudad |
| `/categoria/{slug}` | Landing SEO por categoria |
| `/registrar-negocio` | Registro publico de negocio |
| `/registro/pago-pendiente/{payment}` | Estado de pago post onboarding |
| `/t/{tenant}` | Micrositio publico del negocio |
| `/t/{tenant}/book` | Reservas y citas |
| `/appointments/{appointment}/manage` | Portal publico firmado para gestionar cita |

Nota operativa: en local el micrositio usa fallback path-based con `/t/{tenant}`; la base para subdominios en produccion sigue documentada en configuracion y `routes/public.php`.

### Cuenta web y soporte

Rutas y flujos web auxiliares que siguen activos:

- `/register`, `/login`, `/forgot-password`, `/reset-password/{token}`
- `/verify-email`, `/email/verification-notification`, `/confirm-password`
- `/profile`, `/mi-cuenta`, `/dashboard`
- `/favorites/toggle`
- `/impersonate/leave`

### Servicios auxiliares publicos

Rutas y endpoints auxiliares que conviene tener ubicados:

- `/track/wa/{tenant}`, `/track/call/{tenant}`, `/track/maps/{tenant}`
- `/location/set`, `/location/clear`, `/api/cities`
- `/api/check-slug`
- `/ciudades`

### App Inertia del negocio

Rutas activas bajo `workspace/*`:

- dashboard
- productos
- pedidos
- billing
- marketing
- reportes
- settings
- upgrade
- landing page builder
- descarga de recibos y tickets

### Panel Filament del tenant

Superficie activa bajo `/app` y `/app/{tenant}`:

- dashboard y perfil del tenant
- productos y menu sections
- pedidos
- anuncios
- citas
- campanas
- cupones
- followers y leads
- loyalty programs y loyalty rewards
- servicios y service providers
- tenant users y tenant profile
- manage billing, manage marketing, settings y upgrade to pro

### Panel Filament super admin

Superficie activa bajo `/admin` y `/admin/{tenant}`:

- dashboard
- architecture health
- billing
- manage general settings
- activity logs
- tenants
- users
- payments
- orders
- products
- coupons
- cities
- categories

### API y contratos

| Superficie | Ruta |
| --- | --- |
| REST API | `/api/v1/*` |
| Swagger UI | `/api/documentation` |
| Swagger docs | `/docs` |
| Mock API local | `/mock/{schema}` solo en `local` |
| Webhook de pago | `/api/webhooks/payment` |
| gRPC proto | `proto/identity/service.proto` |

## Capacidades por actor

### Visitante y buyer

- landing y discovery
- directorio con filtros
- micrositios SEO
- favoritos
- reviews
- CTA a WhatsApp, llamada y mapas
- checkout publico
- cupones
- cuenta web y favoritos persistentes
- buyer profile
- reservas y manejo publico de citas
- sitemap, robots y metadatos SEO

### Owner del negocio

- dashboard operativo
- CRUD de productos
- CRUD de menu sections
- gestion de pedidos y estados
- ticket y receipt PDF
- billing, upgrade y reporte de pago manual
- ajustes de perfil, horarios y landing page
- marketing con cupones, campanas y anuncios
- reportes PDF y Excel
- exports descargables
- servicios y proveedores
- loyalty y rewards
- tenant users
- notificaciones en base de datos

### Operacion y backoffice

- administracion de tenants
- administracion de usuarios
- aprobacion y seguimiento de pagos
- activity log
- configuracion global
- impersonation / shadow login
- RBAC con roles `super_admin`, `ventas`, `soporte`, `auditor` y `panel_user`

### Plataforma

- outbox transaccional
- idempotencia
- response cache
- signed URLs y pre-signed uploads
- health checks
- rate limiting contextual
- upcasters y versionado de eventos
- TypeScript sync
- schemas publicables
- PWA
- realtime, broadcast y push
- AI budget enforcement

## Arquitectura

Dovra AI sigue una arquitectura de **modular monolith** con **DDD + Ports and Adapters**.

Topologia principal:

- `app/Domain`: entidades, eventos, enums, value objects y contratos de negocio.
- `app/Application`: comandos, handlers, resultados y orquestacion por modulo.
- `app/Infrastructure`: persistencia, adapters, integraciones externas, jobs, comandos y middleware tecnico.
- `app/Http`: controladores, requests, resources y delivery HTTP.
- `app/Filament`: paneles, pages, resources y widgets.
- `resources/js`: frontend Inertia, React y TypeScript.
- `resources/schemas`: contratos JSON Schema.
- `proto`: contratos gRPC.

Bounded contexts visibles hoy:

- `Identity`
- `Auth`
- `Billing`
- `Sales`
- `Catalog`
- `Booking`
- `Marketing`
- `Analytics`
- `Public`
- `Search`
- `TenantGovernance`
- `AIEnrichment`
- `Reviews`
- `Directory`
- `Interactions`

## Inventario funcional del dominio

Modelos Eloquent presentes en `app/Models`:

- negocio y tenancy: `Tenant`, `TenantUser`, `TenantVisit`, `TenantAnalytic`
- catalogo: `Product`, `ProductImage`, `MenuSection`, `ModifierGroup`, `ModifierOption`, `ProductOption`, `ProductOptionGroup`
- ventas: `Order`, `OrderItem`, `OrderEvent`, `Payment`, `SubscriptionPayment`
- marketing y growth: `Coupon`, `Campaign`, `Announcement`, `Lead`, `Favorite`, `Follow`
- citas y servicios: `Appointment`, `Service`, `ServiceProvider`, `OpeningHour`
- loyalty: `LoyaltyProgram`, `LoyaltyReward`, `LoyaltyLedger`
- identidad y auth: `User`, `OneTimePassword`
- discovery: `Category`, `City`, `Municipality`, `Department`, `Review`, `Interaction`
- integracion y soporte: `WebhookLog`, `ProcessedEvent`

## Frontend y DX

Entrypoints de frontend:

- `resources/js/app.jsx`: shell Inertia/React principal
- `resources/js/app.js`: shell JS usado por Filament/Livewire

Layouts React presentes:

- `AppLayout`
- `AuthenticatedLayout`
- `GuestLayout`
- `PublicLayout`
- `ShopLayout`
- `StoreLayout`
- `TenantLayout`

Paginas principales presentes en `resources/js/Pages`:

- `Welcome`
- `Public/*`
- `Tenant/*`
- `Auth/*`
- `Buyer/*`
- `Billing/*`
- `Profile/*`
- `User/*`

Scripts utiles:

```bash
composer run dev
composer test
composer setup
npm run dev
npm run build
npm run type-check
npm run types:sync
```

Scripts auxiliares en `scripts/`:

- `run-concurrency-tests.ps1`
- `run-concurrency-tests.sh`
- `atomic-commits.ps1`
- `git-atomic-commits.sh`

## API actual

Rutas activas bajo `/api/v1` incluyen:

- `register`
- `user`
- `otp/generate`
- `otp/verify`
- `auth/social`
- `interactions`
- `analytics/track`
- `analytics/conversion`
- `products`
- `coupons`
- `orders`
- `checkout/cart-summary`
- `checkout/remove-coupon`
- `checkout/validate-coupon`
- `settings`
- `reports/*`
- `reports/download/{path}`
- `exports/*`
- `exports/download`
- `notifications/*`
- `ai/generate`
- `ai/optimize-profile`

Controles visibles en la capa API:

- `auth:sanctum` para rutas protegidas
- `tenant.ownership` para aislamiento de tenant
- `signed` para descargas
- `cacheResponse:600` para catalogo publico
- rate limiting especifico para OTP, verificacion, transacciones y trafico tenant-aware

## Contratos y sincronizacion de tipos

Superficies de contratos presentes:

- JSON Schemas en `resources/schemas`
- tipos TS generados en `resources/js/types/generated.d.ts`
- comando `php artisan typescript:transform`
- comando `php artisan schema:generate-types`
- comando `php artisan schema:publish`
- proto gRPC en `proto/identity/service.proto`
- Swagger via `l5-swagger`

## Realtime y push

La capa realtime visible hoy incluye:

- `resources/js/bootstrap.js` inicializa `Laravel Echo` con broadcaster `reverb`.
- `routes/channels.php` define canales privados por tenant, tenant admin, usuario y orden.
- Hay broadcasts para pedidos (`paid`, `cancelled`, `shipped`, `refunded`), low stock, login detectado y actualizaciones de perfil de negocio.
- El modelo `User` ya soporta Web Push subscriptions.

## Asincronia, jobs y notificaciones

Jobs detectados en el repo:

- AI: perfil y enriquecimiento
- analytics: record y sync
- exports: sales export
- reporting: PDF reports
- governance: stale order release y tenant audits
- media: blurhash de productos
- marketing: campaign emails
- appointments: reminders
- payments: webhook processing

Notificaciones presentes:

- `NewOrderNotification`
- `OrderStatusChangedNotification`
- `ReportReadyNotification`
- `ExportReadyNotification`
- `ExportFailedNotification`
- `ProductRejectedNotification`
- `SubscriptionExpiringNotification`

Canales de entrega visibles:

- base de datos
- mail
- broadcast
- web push

Mails presentes:

- bienvenida
- OTP
- cita confirmada
- recordatorio de cita
- payment confirmed / rejected
- subscription expiring / expired
- campaign offer
- login alert

## Scheduler actual

Tareas programadas activas en `routes/console.php`:

- `subscriptions:check-expiry`: diario a las `08:00`
- liberacion de ordenes stale: cada 15 minutos
- `storage:cleanup-orphans --grace=60`: cada hora
- `outbox:process`: cada minuto
- `auth:prune-idempotency --days=30 --chunk=1000`: diario a las `03:30`
- `model:prune`: diario a las `04:00`
- recordatorios de citas: cada hora
- reconciliacion de suscripciones pendientes: cada hora

## Comandos Artisan propios

Comandos detectados en `app/Console/Commands` y `app/Infrastructure/Console/Commands`:

- seguridad y auth: `security:attack-otp`, `auth:clear-otps`, `auth:prune-idempotency`
- subscriptions y billing: `subscriptions:check-expiry`, `subscriptions:expire`, `subscription:lifecycle`, `billing:check-status`, `billing:force-promote`, `billing:prune-data`, `billing:reconcile-intents`
- outbox y eventos: `outbox:process`, `outbox:prune`, `outbox:replay`, `infrastructure:process-outbox`, `infrastructure:outbox-coroner`, `event-sourcing:replay`, `projections:replay`, `app:validate-events`
- schemas y contracts: `schema:generate-enums`, `schema:generate-types`, `schema:generate-sdk`, `schema:publish`, `schema:check-compatibility`
- storage, media y sistema: `storage:cleanup-orphans`, `storage:rescue-proofs`, `media:clean-orphans`, `media:cleanup`, `system:prune`
- DX y utilidades: `make:domain-event`, `seo:generate-sitemap`, `sitemap:generate`, `vito:recalculate-stats`

`vito:recalculate-stats` recalcula ratings y contadores cuando se necesita corregir drift operacional.

## Testing y calidad

El repo contiene **244 archivos de test** al momento de esta actualizacion.

Concentracion principal:

- `tests/Architecture`
- `tests/Contracts`
- `tests/Feature`
- `tests/Arch`
- `tests/Unit`

Herramientas y gates activos:

- Pest 3
- Pest Arch
- PHPUnit 11
- PHPStan nivel 5
- Laravel Pint
- CI en `.github/workflows/ci.yml`

La pipeline principal ejecuta (cuando se dispara manualmente):

- calidad y arquitectura con `pint`, `phpstan` y tests `arch`
- integridad frontend con `php artisan typescript:transform` y `npm run build`
- suite del sistema con `php artisan test --parallel`

Cobertura visible en tests:

- seguridad y rate limiting
- tenancy y aislamiento
- contratos y schemas
- outbox y consistencia eventual
- PWA / push subscriptions
- broadcasting y payload contracts

## Instalacion local

### Requisitos

- PHP 8.2+
- Composer 2
- Node.js 20 recomendado
- npm 10+
- SQLite para arranque rapido o MySQL / MariaDB para entornos mas persistentes

### Variables minimas recomendadas

El `.env.example` es minimo. Para que el proyecto y varios comandos de Artisan arranquen sin excepciones, configura al menos esto en local:

```dotenv
APP_NAME="Dovra AI"
APP_URL=http://localhost:8000
DB_CONNECTION=sqlite
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
PAYMENT_PROVIDER=generic
PAYMENT_WEBHOOK_SECRET=local-secret
```

`PAYMENT_WEBHOOK_SECRET` es importante: el contenedor lo exige al bootear `PaymentWebhookController`, asi que varios comandos de Artisan pueden fallar si esta vacio.

### Arranque rapido con SQLite

```bash
composer install
npm install
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
composer run dev
```

Si usas PowerShell:

```powershell
Copy-Item .env.example .env
New-Item database/database.sqlite -ItemType File -Force
```

### Si prefieres MySQL y Redis

1. Copia `.env.example` a `.env`.
2. Ajusta `DB_*`, `REDIS_*`, `APP_URL`, `SESSION_DOMAIN` y las integraciones necesarias.
3. Define `PAYMENT_WEBHOOK_SECRET`.
4. Ejecuta `php artisan migrate --seed`.
5. Inicia el entorno con `composer run dev`.

### Seeders

`php artisan migrate --seed` crea datos demo locales, incluyendo usuarios de prueba, categorias, ciudades y tenants. Revisa `database/seeders/DatabaseSeeder.php` antes de reutilizar esos datos fuera de local.

## Operacion y despliegue

Directorios de operacion presentes:

- `devops/`: `deploy.sh`, `nginx_app.conf`, `vito-worker.conf`, README de despliegue
- `deployment/nginx/`: config de `reverb_wss.conf`
- `deployment/supervisor/`: workers y proceso de Reverb
- `infrastructure/aws/`: politica de ciclo de vida para S3

Configuracion relacionada con operacion:

- `config/broadcasting.php` y `config/reverb.php` para realtime
- `config/app.php`, `config/tenancy.php` y `config/cors.php` para tenancy y subdominios
- `routes/public.php` conserva la referencia de subdomain routing mientras local usa fallback path-based en `/t/{tenant}`

Consulta tambien:

- `devops/README.md`
- `docs/DEPLOY_JOBS_MIGRATION.md`
- `SECURITY.md`

## Legacy y piezas no primarias que siguen en el repo

Para que no quede nada "suelto", vale la pena dejar registradas estas piezas:

- `routes/public.php` contiene un mapa alterno/legacy de rutas publicas y documentacion de subdominios; la superficie activa principal hoy vive en `routes/web.php`, `routes/tenant.php`, `routes/auth.php`, `routes/api.php` y `routes/api_mock.php`.
- Existen controladores como `Api\V1\SearchController` y `Api\V1\FollowController` que no forman parte tan claramente de la ruta principal activa actual.
- El proyecto conserva vistas Livewire heredadas junto al frontend Inertia/React moderno.
- `AppServiceProvider` todavia documenta y mantiene compatibilidad para aliases de jobs, modelos y migraciones internas.
- El branding viejo `Vito Business OS` sigue disperso por Blade, React, emails, PDF, notificaciones, providers, configs y textos de negocio.

## Documentacion relacionada

- `docs/ARCHITECTURE.md`
- `docs/ADR.md`
- `docs/architecture/EVENTS_GUIDE.md`
- `docs/architecture/decisions/*`
- `docs/code-review/EVENT_REVIEW_CHECKLIST.md`
- `docs/strategy/README.md`
- `docs/strategy/VITO_CODE_VISION_MASTER.md`
- `devops/README.md`
- `SECURITY.md`

## Licencia

MIT.
