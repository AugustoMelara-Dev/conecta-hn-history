<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Domain\TenantGovernance\Services\BusinessOperationsBlueprint;
use App\Domain\TenantGovernance\Services\BusinessSegmentResolver;
use App\Domain\TenantGovernance\Services\TenantOnboardingProgressService;
use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Filament\App\Resources\AppointmentResource;
use App\Filament\App\Resources\CouponResource;
use App\Filament\App\Resources\Leads\LeadResource;
use App\Filament\App\Resources\LocationResource;
use App\Filament\App\Resources\MenuSectionResource;
use App\Filament\App\Resources\OperationalPlaybookTaskResource;
use App\Filament\App\Resources\OrderResource;
use App\Filament\App\Resources\ProductResource;
use App\Filament\App\Resources\ServiceProviderResource;
use App\Filament\App\Resources\ServiceResource;
use App\Filament\App\Resources\TenantUserResource;
use App\Infrastructure\TenantGovernance\Presentation\TenantPlanPresentationReader;
use App\Models\Appointment;
use App\Models\OpeningHour;
use App\Models\OperationalPlaybookTask;
use App\Models\Order;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Throwable;

class Dashboard extends \Filament\Pages\Page
{
    protected static bool $isDiscovered = false;

    protected static string $routePath = '/';

    protected static ?int $navigationSort = -2;

    protected string $view = 'filament.app.pages.dashboard';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Escritorio';

    protected static ?string $title = 'Escritorio';

    public string $tenantName = 'Tu negocio';

    public string $businessType = BusinessType::RETAIL->value;

    public string $businessTypeLabel = 'Tienda y catálogo';

    public string $businessTypeDescription = '';

    public string $segmentKey = 'retail_general';

    public string $segmentLabel = 'Tienda y catálogo';

    public string $segmentTeaser = '';

    public string $experienceTitle = 'Opera tu negocio desde un solo frente';

    public string $experienceCopy = '';

    public string $onboardingTitle = 'Ruta sugerida para tu panel';

    public string $onboardingCopy = '';

    public string $playbookTitle = 'Playbook operativo';

    public string $playbookCopy = '';

    public string $workspaceTitle = 'Frentes que debes activar primero';

    public string $workspaceCopy = '';

    public bool $isPro = false;

    public bool $hasMarketingSuite = false;

    public bool $hasAdvancedBrandingFeature = false;

    public bool $canUpgradeToPro = false;

    public bool $isOpen = true;

    public bool $pickupEnabled = false;

    public bool $deliveryEnabled = false;

    public int $productsCount = 0;

    public int $activeProductsCount = 0;

    public int $sectionsCount = 0;

    public int $followersCount = 0;

    public int $servicesCount = 0;

    public int $activeServicesCount = 0;

    public int $appointmentsCount = 0;

    public int $pendingAppointmentsCount = 0;

    public int $pendingOrdersCount = 0;

    public int $leadsCount = 0;

    public int $campaignsCount = 0;

    public int $staffCount = 0;

    public int $activeStaffCount = 0;

    public int $teamCount = 0;

    public int $locationsCount = 0;

    public bool $hasWhatsApp = false;

    public bool $hasSchedule = false;

    public bool $hasProfileEssentials = false;

    public bool $hasBranding = false;

    public bool $hasPaymentHistory = false;

    public int $healthScore = 0;

    public int $healthCompletionPercent = 0;

    public string $maturityStage = 'launch';

    public string $maturityLabel = 'Arranque';

    public string $maturityTitle = 'Tu operacion todavia esta en fase de arranque';

    public string $maturityCopy = '';

    public string $maturityNextStep = '';

    public int $activationProgress = 0;

    /** @var list<array{key: string, title: string, copy: string, href: string, completed: bool}> */
    public array $activationMilestones = [];

    /** @var array{key: string, title: string, copy: string, href: string} */
    public array $nextBestAction = [
        'key' => 'owner_profile',
        'title' => 'Completa el perfil',
        'copy' => 'Llena la base del negocio para operar sin fricción.',
        'href' => '#',
    ];

    /** @var list<string> */
    public array $businessTypeCapabilities = [];

    /** @var list<string> */
    public array $businessExamples = [];

    /** @var list<array{label: string, value: string, copy: string}> */
    public array $focusMetrics = [];

    /** @var list<array{title: string, copy: string, href: string}> */
    public array $quickActions = [];

    /** @var list<array{title: string, copy: string, href: string, severity: string}> */
    public array $todayQueue = [];

    /** @var list<array{title: string, copy: string, complete: bool, weight: int}> */
    public array $healthChecks = [];

    /** @var list<array{title: string, copy: string, href: string}> */
    public array $growthNudges = [];

    /** @var list<array{title: string, copy: string}> */
    public array $segmentPlaybookSteps = [];

    /** @var list<array{eyebrow: string, title: string, copy: string, href: string, ready: bool, stateLabel: string, stateColor: string}> */
    public array $workspaceModules = [];

    public string $teamTitle = 'Equipo recomendado';

    public string $teamCopy = '';

    /** @var list<array{eyebrow: string, title: string, copy: string, roleLabel: string, when: string, href: string}> */
    public array $teamPlaybooks = [];

    public string $automationTitle = 'Automatizaciones comerciales';

    public string $automationCopy = '';

    /** @var list<array{eyebrow: string, title: string, copy: string, trigger: string, href: string, ready: bool, statusLabel: string, statusColor: string}> */
    public array $automationPlaybooks = [];

    public string $publicUrl = '#';

    public string $productsUrl = '#';

    public string $menuSectionsUrl = '#';

    public string $ordersUrl = '#';

    public string $servicesUrl = '#';

    public string $appointmentsUrl = '#';

    public string $staffUrl = '#';

    public string $teamUrl = '#';

    public string $locationsUrl = '#';

    public string $leadsUrl = '#';

    public string $couponsUrl = '#';

    public string $profileUrl = '#';

    public string $marketingUrl = '#';

    public string $billingUrl = '#';

    public string $operationsUrl = '#';

    public string $growthUrl = '#';

    public string $copilotUrl = '#';

    public int $openPlaybookTasksCount = 0;

    public int $myPlaybookTasksCount = 0;

    public int $overduePlaybookTasksCount = 0;

    /** @var list<array{title: string, copy: string, href: string, severity: string, queue: string, assignee: string, sla: string}> */
    public array $livePlaybookTasks = [];

    /** @var array<string, mixed> */
    private array $segmentProfile = [];

    /** @var array<string, mixed> */
    private array $operationsProfile = [];

    public function mount(): void
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $attributes = $tenant->getAttributes();
        $tenantSlug = (string) ($attributes['slug'] ?? '');
        $this->tenantName = (string) ($attributes['name'] ?? 'Tu negocio');
        $this->hasMarketingSuite = $tenant->hasFeature(TenantFeature::MARKETING_SUITE);
        $this->hasAdvancedBrandingFeature = $tenant->hasFeature(TenantFeature::ADVANCED_BRANDING);
        $this->canUpgradeToPro = $tenant->hasFeature(TenantFeature::UPGRADE_PROMPT);
        $this->isPro = ! $this->canUpgradeToPro;
        $this->businessType = $tenant->business_type->value;

        try {
            $this->productsCount = $tenant->products()->count();
            $this->activeProductsCount = $tenant->products()->where('is_active', true)->count();
            $this->sectionsCount = $tenant->menuSections()->count();
            $this->followersCount = $tenant->followers()->count();
            $this->servicesCount = $tenant->services()->count();
            $this->activeServicesCount = $tenant->services()->where('is_active', true)->count();
            $this->leadsCount = $tenant->leads()->count();
            $this->campaignsCount = $tenant->campaigns()->count();
            $this->teamCount = $tenant->memberships()->count();
            $this->staffCount = $tenant->serviceProviders()->count();
            $this->activeStaffCount = $tenant->serviceProviders()->where('is_active', true)->count();
            $this->locationsCount = $tenant->locations()->count();
        } catch (\Throwable) {
            // Keep the dashboard resilient even when optional modules are unstable.
        }

        try {
            $this->appointmentsCount = Appointment::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->count();

            $this->pendingAppointmentsCount = Appointment::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->whereIn('status', [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED])
                ->count();

            $this->pendingOrdersCount = Order::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->where('status', Order::STATUS_PENDING)
                ->count();
        } catch (\Throwable) {
            // Orders and appointments are optional for some tenants.
        }

        $this->hasWhatsApp = filled($attributes['whatsapp_number'] ?? null);
        $this->hasSchedule = filled($attributes['simple_schedule'] ?? null) || $this->tenantHasOpeningHours($tenant);
        $this->hasProfileEssentials = filled($attributes['description'] ?? null) && filled($attributes['address'] ?? null);
        $this->hasBranding = filled($attributes['logo_path'] ?? null)
            || filled($attributes['hero_image'] ?? null)
            || filled($attributes['slogan'] ?? null);
        $this->pickupEnabled = (bool) ($tenant->pickup_enabled ?? false);
        $this->deliveryEnabled = (bool) ($tenant->delivery_enabled ?? false);
        $this->isOpen = $this->resolveOpenStatus($tenant);
        $this->hasPaymentHistory = SubscriptionPayment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->exists();

        $this->publicUrl = $this->safeRoute('tenant.microsite.show', ['tenant' => $tenantSlug]);
        $this->productsUrl = $this->safeResourceUrl(ProductResource::class, ['tenant' => $tenantSlug]);
        $this->menuSectionsUrl = $this->safeResourceUrl(MenuSectionResource::class, ['tenant' => $tenantSlug]);
        $this->ordersUrl = $this->safeResourceUrl(OrderResource::class, ['tenant' => $tenantSlug]);
        $this->servicesUrl = $this->safeResourceUrl(ServiceResource::class, ['tenant' => $tenantSlug]);
        $this->appointmentsUrl = $this->safeResourceUrl(AppointmentResource::class, ['tenant' => $tenantSlug]);
        $this->staffUrl = $this->safeResourceUrl(ServiceProviderResource::class, ['tenant' => $tenantSlug]);
        $this->teamUrl = $this->safeResourceUrl(TenantUserResource::class, ['tenant' => $tenantSlug]);
        $this->locationsUrl = $this->safeResourceUrl(LocationResource::class, ['tenant' => $tenantSlug]);
        $this->leadsUrl = $this->safeResourceUrl(LeadResource::class, ['tenant' => $tenantSlug]);
        $this->couponsUrl = $this->safeResourceUrl(CouponResource::class, ['tenant' => $tenantSlug]);
        $this->profileUrl = $this->safeRoute('filament.app.pages.settings', ['tenant' => $tenantSlug]);
        $this->marketingUrl = $this->safeRoute('filament.app.pages.manage-marketing', ['tenant' => $tenantSlug]);
        $this->billingUrl = $this->safeRoute('filament.app.pages.manage-billing', ['tenant' => $tenantSlug]);
        $this->operationsUrl = $this->safeResourceUrl(OperationalPlaybookTaskResource::class, ['tenant' => $tenantSlug]);
        $this->growthUrl = $this->safeRoute('filament.app.pages.growth-center', ['tenant' => $tenantSlug]);
        $this->copilotUrl = BusinessCopilot::getUrl(['tenant' => $tenantSlug]);
        $this->loadPlaybookExecutionState($tenant);

        $this->configureExperience($tenant);
        $this->hydrateOnboardingState($tenant);
    }

    private function configureExperience(Tenant $tenant): void
    {
        $manifest = app(RubroManifestReaderInterface::class)->forTenant($tenant);
        $presentation = (array) data_get($manifest, 'presentation', []);
        $config = config("business-types.options.{$this->businessType}", []);

        $this->businessTypeLabel = (string) ($presentation['label'] ?? $config['label'] ?? 'Negocio');
        $this->businessTypeDescription = (string) ($presentation['description'] ?? $config['description'] ?? 'Organiza tu operacion desde una base clara.');
        $this->businessTypeCapabilities = collect($config['capabilities'] ?? [])
            ->filter(static fn (mixed $item): bool => is_string($item) && $item !== '')
            ->values()
            ->all();
        $this->businessExamples = collect($presentation['examples'] ?? $config['examples'] ?? [])
            ->filter(static fn (mixed $item): bool => is_string($item) && $item !== '')
            ->values()
            ->all();

        $segmentResolver = app(BusinessSegmentResolver::class);
        $this->segmentKey = $segmentResolver->resolveForTenant($tenant);
        $this->segmentProfile = array_replace_recursive(
            $segmentResolver->internalProfile($this->segmentKey, $this->businessType),
            app(RubroManifestReaderInterface::class)->businessAppForTenant($tenant),
        );
        $this->segmentLabel = (string) ($this->segmentProfile['label'] ?? $this->businessTypeLabel);
        $this->segmentTeaser = (string) ($this->segmentProfile['teaser'] ?? '');
        $this->operationsProfile = app(BusinessOperationsBlueprint::class)->forSegment($this->segmentKey, $this->businessType);

        match ($this->businessType) {
            BusinessType::FOOD->value => $this->configureFoodExperience(),
            BusinessType::SERVICE->value => $this->configureServiceExperience(),
            BusinessType::HYBRID->value => $this->configureHybridExperience(),
            default => $this->configureRetailExperience(),
        };

        $this->applySegmentExperience();
        $this->buildHealthState();
        $this->applyOperationsBlueprint();
        $this->buildMaturityState();
        $this->buildOperationalQueue();
    }

    private function configureRetailExperience(): void
    {
        $this->experienceTitle = 'Tu centro de operación para catálogo, pedidos y atención';
        $this->experienceCopy = 'Ordena tu inventario, define cómo vendes y deja lista una vitrina clara para tiendas, ferreterías, tecnología, moda y negocios similares.';

        $this->focusMetrics = [
            $this->metric('Productos', (string) $this->productsCount, "{$this->activeProductsCount} activos en tu catálogo."),
            $this->metric('Colecciones', (string) $this->sectionsCount, 'Organizan tu oferta para que el cliente encuentre más rápido.'),
            $this->metric('Pedidos pendientes', (string) $this->pendingOrdersCount, 'Confirmaciones u órdenes que todavía requieren atención.'),
            $this->metric('Plan', $this->planMetricValue(), $this->planMetricCopy()),
        ];

        $this->quickActions = [
            $this->action('Administrar productos', 'Publica, edita precios y mantén tu vitrina actualizada.', $this->productsUrl),
            $this->action('Ordenar catálogo', 'Agrupa por líneas, marcas o categorías para vender mejor.', $this->menuSectionsUrl),
            $this->action('Revisar pedidos', 'Atiende compras pendientes antes de que se enfríen.', $this->ordersUrl),
            $this->action('Configurar sucursales', 'Prepara pickup, cobertura y puntos de atención.', $this->locationsUrl),
        ];
    }

    private function configureFoodExperience(): void
    {
        $this->experienceTitle = 'Tu frente operativo para menú, pedidos y servicio';
        $this->experienceCopy = 'Prioriza lo que mueve un restaurante o cafetería: menú claro, tiempos de respuesta, horarios confiables y canales de contacto que conviertan.';

        $this->focusMetrics = [
            $this->metric('Platos y productos', (string) $this->productsCount, "{$this->activeProductsCount} visibles para venta."),
            $this->metric('Secciones del menú', (string) $this->sectionsCount, 'Entradas, bebidas, combos o estaciones.'),
            $this->metric('Pedidos pendientes', (string) $this->pendingOrdersCount, 'Órdenes por confirmar o despachar.'),
            $this->metric('Plan', $this->planMetricValue(), $this->planMetricCopy()),
        ];

        $this->quickActions = [
            $this->action('Editar menú', 'Actualiza platos, precios y disponibilidad sin perder tiempo.', $this->productsUrl),
            $this->action('Secciones del menú', 'Ordena bebidas, entradas, fuertes y postres.', $this->menuSectionsUrl),
            $this->action('Gestionar pedidos', 'Confirma, prepara y entrega desde un solo lugar.', $this->ordersUrl),
            $this->action('Compartir QR y marketing', 'Mueve más mesas y pedidos desde tu página pública.', $this->marketingUrl),
        ];
    }

    private function configureServiceExperience(): void
    {
        $this->experienceTitle = 'Tu centro de operación para servicios, agenda y seguimiento';
        $this->experienceCopy = 'Diseñado para consultorías, abogados, clínicas, belleza y negocios donde la agenda y la confianza pesan tanto como la venta.';

        $this->focusMetrics = [
            $this->metric('Servicios', (string) $this->servicesCount, "{$this->activeServicesCount} activos para reservar."),
            $this->metric('Citas próximas', (string) $this->pendingAppointmentsCount, 'Reservas pendientes o confirmadas en agenda.'),
            $this->metric('Staff operativo', (string) $this->activeStaffCount, "{$this->staffCount} perfiles configurados."),
            $this->metric('Plan', $this->planMetricValue(), $this->planMetricCopy()),
        ];

        $this->quickActions = [
            $this->action('Catálogo de servicios', 'Define qué ofreces, cuánto dura y cuánto cuesta.', $this->servicesUrl),
            $this->action('Agenda de citas', 'Confirma, completa o reordena tu operación diaria.', $this->appointmentsUrl),
            $this->action('Personal y disponibilidad', 'Asigna profesionales, horarios y cobertura.', $this->staffUrl),
            $this->action('Prospectos y seguimiento', 'Organiza consultas y oportunidades de cierre.', $this->leadsUrl),
        ];
    }

    private function configureHybridExperience(): void
    {
        $this->experienceTitle = 'Tu panel mixto para vender productos y coordinar servicios';
        $this->experienceCopy = 'Ideal para negocios que combinan tienda, agenda, entregas o atención técnica. Aquí la clave es balancear catálogo, operación y seguimiento.';

        $this->focusMetrics = [
            $this->metric('Productos', (string) $this->productsCount, "{$this->activeProductsCount} activos en tu catálogo."),
            $this->metric('Servicios', (string) $this->servicesCount, "{$this->activeServicesCount} listos para reservar."),
            $this->metric('Agenda activa', (string) $this->pendingAppointmentsCount, 'Reservas que hoy requieren confirmación o ejecución.'),
            $this->metric('Plan', $this->planMetricValue(), $this->planMetricCopy()),
        ];

        $this->quickActions = [
            $this->action('Productos y catálogo', 'Mantén al día lo que vendes físicamente o por pedido.', $this->productsUrl),
            $this->action('Servicios y agenda', 'Organiza reservas, técnicos o consultores.', $this->servicesUrl),
            $this->action('Pedidos y atención', 'Controla órdenes pendientes y seguimiento comercial.', $this->ordersUrl),
            $this->action('Equipo y sedes', 'Coordina staff, sucursales y cobertura operativa.', $this->teamUrl),
        ];
    }

    private function applySegmentExperience(): void
    {
        $this->experienceTitle = (string) ($this->segmentProfile['dashboard_title'] ?? $this->experienceTitle);
        $this->experienceCopy = (string) ($this->segmentProfile['dashboard_copy'] ?? $this->experienceCopy);
        $this->onboardingTitle = (string) ($this->segmentProfile['onboarding_title'] ?? 'Ruta sugerida para tu panel');
        $this->onboardingCopy = (string) ($this->segmentProfile['onboarding_copy'] ?? 'Estas son las áreas que más valor te dan según tu operación.');
        $this->playbookTitle = (string) ($this->segmentProfile['playbook_title'] ?? 'Playbook operativo');
        $this->playbookCopy = (string) ($this->segmentProfile['playbook_copy'] ?? 'Empieza por los frentes que más impacto operativo y comercial generan.');
        $this->workspaceTitle = (string) ($this->segmentProfile['workspace_title'] ?? 'Frentes que debes activar primero');
        $this->workspaceCopy = (string) ($this->segmentProfile['workspace_copy'] ?? 'Estos módulos te dan la mejor palanca inicial para crecer con menos fricción.');

        $this->segmentPlaybookSteps = collect((array) ($this->segmentProfile['playbook_steps'] ?? []))
            ->map(static fn (array $item): array => [
                'title' => (string) ($item['title'] ?? 'Paso operativo'),
                'copy' => (string) ($item['copy'] ?? ''),
            ])
            ->filter(static fn (array $item): bool => $item['title'] !== '')
            ->values()
            ->all();

        $this->workspaceModules = collect((array) ($this->segmentProfile['recommended_modules'] ?? []))
            ->map(fn (mixed $item): array => $this->workspaceModule($item))
            ->filter(static fn (array $item): bool => $item['title'] !== '')
            ->values()
            ->all();
    }

    private function applyOperationsBlueprint(): void
    {
        $this->teamTitle = (string) ($this->operationsProfile['team_title'] ?? 'Equipo recomendado');
        $this->teamCopy = (string) ($this->operationsProfile['team_copy'] ?? 'Asigna responsabilidades claras para que el panel no dependa de una sola persona.');
        $this->teamPlaybooks = collect((array) ($this->operationsProfile['team_playbook'] ?? []))
            ->map(fn (array $item): array => $this->teamPlaybookCard($item))
            ->filter(static fn (array $item): bool => $item['title'] !== '')
            ->values()
            ->all();

        $this->automationTitle = (string) ($this->operationsProfile['automation_title'] ?? 'Automatizaciones comerciales');
        $this->automationCopy = (string) ($this->operationsProfile['automation_copy'] ?? 'Empieza por automatizaciones que te ayuden a responder mejor y vender con continuidad.');
        $this->automationPlaybooks = collect((array) ($this->operationsProfile['automation_playbook'] ?? []))
            ->map(fn (array $item): array => $this->automationPlaybookCard($item))
            ->filter(static fn (array $item): bool => $item['title'] !== '')
            ->values()
            ->all();
    }

    private function buildMaturityState(): void
    {
        $readyWorkspaceCount = collect($this->workspaceModules)
            ->filter(static fn (array $item): bool => $item['ready'])
            ->count();

        $readyAutomationCount = collect($this->automationPlaybooks)
            ->filter(static fn (array $item): bool => $item['ready'])
            ->count();

        if ($this->healthScore >= 75 && $readyWorkspaceCount >= 3 && ($this->isPro || $readyAutomationCount >= 2)) {
            $this->maturityStage = 'scale';
            $this->maturityLabel = 'Escala';
            $this->maturityTitle = 'Tu operacion ya tiene base para escalar';
            $this->maturityCopy = 'Ya no estas armando el negocio: ahora te conviene estandarizar equipo, seguimiento y marketing para crecer sin perder consistencia.';
        } elseif ($this->healthScore >= 45 && $readyWorkspaceCount >= 2) {
            $this->maturityStage = 'traction';
            $this->maturityLabel = 'Traccion';
            $this->maturityTitle = 'Tu operacion ya entro en fase de traccion';
            $this->maturityCopy = 'El negocio ya se siente mas real. Ahora el salto importante es repartir responsabilidades y convertir el seguimiento en rutina.';
        } else {
            $this->maturityStage = 'launch';
            $this->maturityLabel = 'Arranque';
            $this->maturityTitle = 'Tu operacion todavia esta en fase de arranque';
            $this->maturityCopy = 'La prioridad sigue siendo activar lo esencial del rubro, cerrar bien el primer flujo comercial y evitar que todo dependa de memoria.';
        }

        $nextWorkspace = collect($this->workspaceModules)
            ->first(static fn (array $item): bool => ! $item['ready']);
        $nextAutomation = collect($this->automationPlaybooks)
            ->first(static fn (array $item): bool => ! $item['ready']);

        if (is_array($nextWorkspace)) {
            $this->maturityNextStep = "Siguiente salto: activa {$nextWorkspace['title']} para consolidar tu {$this->segmentLabel}.";

            return;
        }

        if (is_array($nextAutomation)) {
            $this->maturityNextStep = "Siguiente salto: monta {$nextAutomation['title']} para vender con mas continuidad.";

            return;
        }

        $this->maturityNextStep = 'Siguiente salto: convierte tu operacion en una maquina repetible con equipo claro, seguimiento y marketing disciplinado.';
    }

    private function planMetricValue(): string
    {
        return (string) ($this->planPresentation()['label'] ?? ($this->isPro ? 'PRO' : 'Base'));
    }

    private function planMetricCopy(): string
    {
        $plan = $this->planPresentation();

        if ($this->isPro) {
            return (string) ($plan['marketingCopy'] ?? 'Herramientas premium activas.');
        }

        return (string) ($plan['catalogCopy'] ?? 'Modo esencial listo para crecer.');
    }

    private function brandingNudgeCopy(): string
    {
        return (string) ($this->planPresentation()['brandingCopy'] ?? 'Sube portada o eslogan para que tu negocio se vea mas solido.');
    }

    private function upgradeNudgeCopy(): string
    {
        return (string) ($this->planPresentation()['marketingCopy'] ?? 'Activa marketing y seguimiento para vender desde el panel.');
    }

    /**
     * @return array<string, mixed>
     */
    private function planPresentation(): array
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        return app(TenantPlanPresentationReader::class)->forTenant($tenant);
    }

    private function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function tenantHasOpeningHours(Tenant $tenant): bool
    {
        try {
            return OpeningHour::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolveOpenStatus(Tenant $tenant): bool
    {
        try {
            $now = now(OpeningHour::BUSINESS_TIMEZONE);

            $todayHours = OpeningHour::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->where('day_of_week', $now->dayOfWeek)
                ->get();

            if ($todayHours->isEmpty()) {
                return true;
            }

            return $todayHours->contains(
                fn (OpeningHour $openingHour): bool => $openingHour->isOpenAt($now)
            );
        } catch (\Throwable) {
            return true;
        }
    }

    private function safeRoute(string $name, array $parameters = []): string
    {
        try {
            return route($name, $parameters);
        } catch (\Throwable) {
            return '#';
        }
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    private function safeResourceUrl(string $resource, array $parameters = []): string
    {
        try {
            return $resource::getUrl('index', $parameters);
        } catch (\Throwable) {
            return '#';
        }
    }

    private function buildHealthState(): void
    {
        $configuredChecks = collect((array) ($this->segmentProfile['health_checks'] ?? []))
            ->map(fn (array $definition): array => $this->configuredCheck($definition))
            ->filter(static fn (array $item): bool => $item['title'] !== '')
            ->values()
            ->all();

        $this->healthChecks = $configuredChecks !== [] ? $configuredChecks : [
            $this->check('Activa el frente principal', $this->productsCount > 0 || $this->servicesCount > 0, 'Tu negocio ya tiene una oferta inicial visible.', 'Activa al menos un producto o servicio para no abrir vacío.', 30),
            $this->check('Activa un canal directo', $this->hasWhatsApp, 'Tu canal de contacto ya está listo para convertir.', 'Agrega WhatsApp para mover conversaciones con menos fricción.', 20),
            $this->check('Completa perfil y confianza', $this->hasProfileEssentials && $this->hasBranding, 'Tu negocio ya transmite una presencia más completa.', 'Completa descripción, dirección y branding para verte más sólido.', 25),
            $this->check('Aclara cómo operas', $this->hasSchedule || $this->locationsCount > 0 || $this->pickupEnabled || $this->deliveryEnabled, 'Tu operación ya se entiende mejor desde el panel y el sitio.', 'Define horarios, cobertura o modalidad de atención.', 25),
        ];

        $completedWeight = collect($this->healthChecks)
            ->filter(fn (array $item): bool => $item['complete'])
            ->sum('weight');

        $this->healthScore = (int) min(100, $completedWeight);
        $this->healthCompletionPercent = $this->healthScore;

        $nudges = [];

        foreach ($this->workspaceModules as $module) {
            if ($module['ready']) {
                continue;
            }

            $nudges[] = $this->action("Activa {$module['title']}", $module['copy'], $module['href']);

            if (count($nudges) === 2) {
                break;
            }
        }

        if (! $this->hasWhatsApp) {
            $nudges[] = $this->action('Configura tu WhatsApp', 'Es la forma más directa de convertir visitas en conversaciones.', $this->profileUrl);
        }

        if (! $this->hasBranding && $this->hasAdvancedBrandingFeature) {
            $nudges[] = $this->action('Aprovecha tu branding', $this->brandingNudgeCopy(), $this->marketingUrl);
        }

        if ($this->canUpgradeToPro) {
            $nudges[] = $this->action('Activa tu siguiente nivel comercial', $this->upgradeNudgeCopy(), $this->billingUrl);
        }

        $this->growthNudges = array_slice($nudges, 0, 3);
    }

    private function loadPlaybookExecutionState(Tenant $tenant): void
    {
        try {
            $activeStatuses = [
                OperationalPlaybookTask::STATUS_OPEN,
                OperationalPlaybookTask::STATUS_IN_PROGRESS,
            ];

            $query = OperationalPlaybookTask::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', (string) $tenant->getKey())
                ->whereIn('status', $activeStatuses)
                ->with('assignee');

            /** @var EloquentCollection<int, OperationalPlaybookTask> $activeTasks */
            $activeTasks = $this->applyPlaybookPriorityOrdering(clone $query)
                ->limit(4)
                ->get();

            $this->openPlaybookTasksCount = (clone $query)->count();
            $this->overduePlaybookTasksCount = (clone $query)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count();

            $currentUser = auth()->user();
            $this->myPlaybookTasksCount = $currentUser instanceof User
                ? (clone $query)->where('assigned_user_id', (string) $currentUser->getKey())->count()
                : 0;

            $this->livePlaybookTasks = $activeTasks
                ->map(fn (OperationalPlaybookTask $task): array => $this->playbookDashboardCard($task))
                ->values()
                ->all();
        } catch (Throwable $exception) {
            report($exception);
            $this->openPlaybookTasksCount = 0;
            $this->myPlaybookTasksCount = 0;
            $this->overduePlaybookTasksCount = 0;
            $this->livePlaybookTasks = [];
        }
    }

    private function buildOperationalQueue(): void
    {
        $queue = [];

        foreach ($this->livePlaybookTasks as $task) {
            $queue[] = [
                'title' => $task['title'],
                'copy' => $task['copy'],
                'href' => $task['href'],
                'severity' => $task['severity'],
            ];
        }

        if ($this->pendingOrdersCount > 0) {
            $queue[] = [
                'title' => 'Hay pedidos esperando atención',
                'copy' => 'Confirma o despacha antes de que la venta pierda velocidad.',
                'href' => $this->ordersUrl,
                'severity' => 'danger',
            ];
        }

        if ($this->pendingAppointmentsCount > 0) {
            $queue[] = [
                'title' => 'Tienes citas por confirmar',
                'copy' => 'Hay reservas que necesitan respuesta y seguimiento hoy.',
                'href' => $this->appointmentsUrl,
                'severity' => 'danger',
            ];
        }

        foreach ($this->workspaceModules as $index => $module) {
            if ($module['ready']) {
                continue;
            }

            $queue[] = [
                'title' => "Activa {$module['title']}",
                'copy' => $module['copy'],
                'href' => $module['href'],
                'severity' => $index === 0 ? 'warning' : 'info',
            ];
        }

        $this->todayQueue = collect($queue)
            ->unique('title')
            ->take(4)
            ->values()
            ->all();

        if ($this->todayQueue !== []) {
            return;
        }

        $this->todayQueue = [
            [
                'title' => 'Buen punto de partida',
                'copy' => 'Tu rubro ya cubre lo esencial. Ahora conviene sostener catálogo, agenda o pedidos con disciplina diaria.',
                'href' => $this->publicUrl,
                'severity' => 'success',
            ],
        ];
    }

    private function hydrateOnboardingState(Tenant $tenant): void
    {
        try {
            $snapshot = app(TenantOnboardingProgressService::class)->persist($tenant, auth()->user());
            $this->activationProgress = (int) ($snapshot['progress'] ?? 0);
            $this->activationMilestones = (array) ($snapshot['milestones'] ?? []);
            $this->nextBestAction = (array) ($snapshot['nextAction'] ?? $this->nextBestAction);
        } catch (\Throwable) {
            // Dashboard must remain available even if onboarding persistence fails.
            $this->activationProgress = 0;
            $this->activationMilestones = [];
        }
    }

    /**
     * @return array{eyebrow: string, title: string, copy: string, href: string, ready: bool, stateLabel: string, stateColor: string}
     */
    private function workspaceModule(mixed $definition): array
    {
        if (is_string($definition)) {
            $definition = [
                'route_key' => $definition,
                'state' => $definition,
                'title' => \Illuminate\Support\Str::of($definition)->replace('_', ' ')->title()->value(),
                'copy' => 'Modulo recomendado por RubroManifest para este tipo de negocio.',
            ];
        }

        if (! is_array($definition)) {
            $definition = [];
        }

        $routeKey = (string) ($definition['route_key'] ?? '');
        $state = (string) ($definition['state'] ?? '');
        $ready = $this->resolveOperationalState($state);

        return [
            'eyebrow' => (string) ($definition['eyebrow'] ?? 'Módulo'),
            'title' => (string) ($definition['title'] ?? ''),
            'copy' => (string) ($definition['copy'] ?? ''),
            'href' => $this->resolveWorkspaceHref($routeKey),
            'ready' => $ready,
            'stateLabel' => $ready ? 'Listo' : 'Pendiente',
            'stateColor' => $ready ? 'success' : 'warning',
        ];
    }

    /**
     * @return array{eyebrow: string, title: string, copy: string, roleLabel: string, when: string, href: string}
     */
    private function teamPlaybookCard(array $definition): array
    {
        $roleValue = (string) ($definition['role'] ?? '');
        $roleLabel = TenantUserResource::getRoleLabel($roleValue);

        return [
            'eyebrow' => (string) ($definition['eyebrow'] ?? 'Equipo'),
            'title' => (string) ($definition['title'] ?? ''),
            'copy' => (string) ($definition['copy'] ?? ''),
            'roleLabel' => $roleLabel,
            'when' => (string) ($definition['when'] ?? ''),
            'href' => $this->resolveWorkspaceHref((string) ($definition['route_key'] ?? 'team')),
        ];
    }

    /**
     * @return array{eyebrow: string, title: string, copy: string, trigger: string, href: string, ready: bool, statusLabel: string, statusColor: string}
     */
    private function automationPlaybookCard(array $definition): array
    {
        $state = (string) ($definition['state'] ?? '');
        $ready = $this->resolveOperationalState($state);

        return [
            'eyebrow' => (string) ($definition['eyebrow'] ?? 'Automatizacion'),
            'title' => (string) ($definition['title'] ?? ''),
            'copy' => (string) ($definition['copy'] ?? ''),
            'trigger' => (string) ($definition['trigger'] ?? ''),
            'href' => $this->resolveWorkspaceHref((string) ($definition['route_key'] ?? 'marketing')),
            'ready' => $ready,
            'statusLabel' => $ready ? 'Base lista' : 'Pendiente',
            'statusColor' => $ready ? 'success' : 'warning',
        ];
    }

    /**
     * @return array{title: string, copy: string, href: string, severity: string, queue: string, assignee: string, sla: string}
     */
    private function playbookDashboardCard(OperationalPlaybookTask $task): array
    {
        $assignee = $task->assignee instanceof User ? $task->assignee->name : 'Sin asignar';
        $sla = 'Sin SLA';
        $dueAt = $this->taskCarbon($task->due_at);

        if ($dueAt instanceof Carbon) {
            $sla = $task->isOverdue()
                ? 'SLA vencido'
                : 'SLA '.(string) $dueAt->diffForHumans();
        }

        return [
            'title' => (string) $task->title,
            'copy' => (string) ($task->summary ?? ''),
            'href' => $this->resolvePlaybookHref($task),
            'severity' => $this->playbookSeverity($task),
            'queue' => (string) $task->queue_label,
            'assignee' => $assignee,
            'sla' => $sla,
        ];
    }

    private function resolvePlaybookHref(OperationalPlaybookTask $task): string
    {
        $href = $this->resolveWorkspaceHref((string) ($task->route_key ?? ''));

        return $href !== '#' ? $href : $this->operationsUrl;
    }

    private function playbookSeverity(OperationalPlaybookTask $task): string
    {
        if ($task->isOverdue()) {
            return 'danger';
        }

        return match ((string) $task->priority) {
            OperationalPlaybookTask::PRIORITY_CRITICAL => 'danger',
            OperationalPlaybookTask::PRIORITY_HIGH => 'warning',
            default => 'info',
        };
    }

    private function applyPlaybookPriorityOrdering(Builder $query): Builder
    {
        return $query
            ->orderByRaw("
                case priority
                    when 'critical' then 1
                    when 'high' then 2
                    else 3
                end
            ")
            ->orderByRaw("
                case
                    when due_at is null then 2
                    when due_at < {$this->currentTimestampSql()} then 0
                    else 1
                end
            ")
            ->orderBy('due_at');
    }

    private function currentTimestampSql(): string
    {
        return match ((string) config('database.default', 'mysql')) {
            'sqlite' => 'CURRENT_TIMESTAMP',
            default => 'now()',
        };
    }

    private function taskCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    private function resolveWorkspaceHref(string $routeKey): string
    {
        return match ($routeKey) {
            'products' => $this->productsUrl,
            'menuSections' => $this->menuSectionsUrl,
            'orders' => $this->ordersUrl,
            'services' => $this->servicesUrl,
            'appointments' => $this->appointmentsUrl,
            'staff' => $this->staffUrl,
            'team' => $this->teamUrl,
            'locations' => $this->locationsUrl,
            'leads' => $this->leadsUrl,
            'coupons' => $this->couponsUrl,
            'profile' => $this->profileUrl,
            'marketing' => $this->marketingUrl,
            'billing' => $this->billingUrl,
            'operations' => $this->operationsUrl,
            'public' => $this->publicUrl,
            default => '#',
        };
    }

    private function resolveOperationalState(string $state): bool
    {
        return match ($state) {
            'products' => $this->productsCount > 0,
            'sections' => $this->sectionsCount > 0,
            'services' => $this->servicesCount > 0,
            'leads' => $this->leadsCount > 0,
            'campaigns' => $this->campaignsCount > 0,
            'followers' => $this->followersCount > 0,
            'staff' => $this->staffCount > 0,
            'team' => $this->teamCount > 0,
            'team_or_staff' => $this->teamCount > 0 || $this->staffCount > 0,
            'orders_activity' => $this->pendingOrdersCount > 0 || $this->productsCount > 0,
            'mixed_activity' => $this->pendingOrdersCount > 0 || $this->pendingAppointmentsCount > 0 || ($this->productsCount > 0 && $this->servicesCount > 0),
            'booking_ready' => $this->servicesCount > 0 && ($this->staffCount > 0 || $this->hasSchedule),
            'retail_sales_base' => $this->productsCount > 0 && $this->hasWhatsApp,
            'food_sales_base' => $this->productsCount > 0 && ($this->hasWhatsApp || $this->pickupEnabled || $this->deliveryEnabled),
            'service_sales_base' => $this->servicesCount > 0 && $this->hasWhatsApp,
            'hybrid_sales_base' => $this->productsCount > 0 && $this->servicesCount > 0 && $this->hasWhatsApp,
            'pro_marketing' => $this->hasMarketingSuite,
            'audience_ready' => $this->followersCount > 0 || $this->leadsCount > 0,
            'whatsapp' => $this->hasWhatsApp,
            'schedule' => $this->hasSchedule,
            'branding' => $this->hasBranding,
            'profile_branding' => $this->hasProfileEssentials && $this->hasBranding,
            'profile_branding_whatsapp' => $this->hasProfileEssentials && $this->hasBranding && $this->hasWhatsApp,
            'service_channel' => $this->hasWhatsApp && $this->hasSchedule,
            'pickup_delivery' => $this->pickupEnabled || $this->deliveryEnabled,
            'coverage' => $this->pickupEnabled || $this->deliveryEnabled || $this->locationsCount > 0,
            'hybrid_coverage' => $this->hasSchedule && ($this->pickupEnabled || $this->deliveryEnabled || $this->locationsCount > 0),
            default => false,
        };
    }

    /**
     * @return array{title: string, copy: string, complete: bool, weight: int}
     */
    private function configuredCheck(array $definition): array
    {
        $title = (string) ($definition['title'] ?? '');
        $state = (string) ($definition['state'] ?? '');
        $complete = $this->resolveOperationalState($state);

        return $this->check(
            $title,
            $complete,
            (string) ($definition['complete_copy'] ?? ''),
            (string) ($definition['pending_copy'] ?? ''),
            (int) ($definition['weight'] ?? 0),
        );
    }

    /**
     * @return array{label: string, value: string, copy: string}
     */
    private function metric(string $label, string $value, string $copy): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'copy' => $copy,
        ];
    }

    /**
     * @return array{title: string, copy: string, href: string}
     */
    private function action(string $title, string $copy, string $href): array
    {
        return [
            'title' => $title,
            'copy' => $copy,
            'href' => $href,
        ];
    }

    /**
     * @return array{title: string, copy: string, complete: bool, weight: int}
     */
    private function check(string $title, bool $complete, string $completeCopy, string $pendingCopy, int $weight): array
    {
        return [
            'title' => $title,
            'copy' => $complete ? $completeCopy : $pendingCopy,
            'complete' => $complete,
            'weight' => $weight,
        ];
    }

    public static function getRoutePath(Panel $panel): string
    {
        return static::$routePath;
    }
}
