<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Services\BusinessGrowthBlueprint;
use App\Domain\TenantGovernance\Services\CustomerProfileManager;
use App\Domain\TenantGovernance\Services\BusinessSegmentResolver;
use App\Filament\App\Resources\AppointmentResource;
use App\Filament\App\Resources\CustomerProfileResource;
use App\Filament\App\Resources\CustomerTouchpointResource;
use App\Filament\App\Resources\CouponResource;
use App\Filament\App\Resources\FollowerResource;
use App\Filament\App\Resources\Leads\LeadResource;
use App\Filament\App\Resources\LoyaltyProgramResource;
use App\Filament\App\Resources\OperationalPlaybookTaskResource;
use App\Filament\App\Resources\OrderResource;
use App\Models\Appointment;
use App\Models\Campaign;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Follow;
use App\Models\Lead;
use App\Models\LoyaltyProgram;
use App\Models\OperationalPlaybookTask;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantAnalytic;
use App\Models\Waitlist;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GrowthCenter extends Page
{
    protected static bool $isDiscovered = false;

    protected static string $routePath = 'growth-center';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Crecimiento';

    protected static ?string $title = 'Centro de crecimiento';

    protected string $view = 'filament.app.pages.growth-center';

    public string $tenantName = 'Tu negocio';

    public string $businessType = BusinessType::RETAIL->value;

    public string $businessTypeLabel = 'Retail';

    public string $segmentKey = 'retail_growth';

    public string $segmentLabel = 'Retail';

    public string $headline = 'CRM y crecimiento';

    public string $copy = '';

    public string $crmTitle = 'Pipeline comercial';

    public string $crmCopy = '';

    public string $analyticsTitle = 'Analitica PYME';

    public string $analyticsCopy = '';

    public string $automationTitle = 'Automatizaciones externas';

    public string $automationCopy = '';

    public bool $isPro = false;

    public bool $hasWhatsApp = false;

    public bool $hasLoyaltyProgram = false;

    public int $visits30d = 0;

    public int $totalVisits30d = 0;

    public int $orders30d = 0;

    public int $appointments30d = 0;

    public int $grossSalesOrders30d = 0;

    public float $grossSales30d = 0.0;

    public float $averageTicket30d = 0.0;

    public int $distinctCustomersCount = 0;

    public int $repeatCustomerCount = 0;

    public int $reactivationPoolCount = 0;

    public int $newLeadsCount = 0;

    public int $contactedLeadsCount = 0;

    public int $closedLeadsCount = 0;

    public int $lostLeadsCount = 0;

    public int $followersCount = 0;

    public int $waitlistCount = 0;

    public int $crmProfilesCount = 0;

    public int $touchpoints30d = 0;

    public int $campaignsSent30d = 0;

    public int $openPlaybooksCount = 0;

    public int $overduePlaybooksCount = 0;

    public string $topLeadSource = 'Sin datos';

    public string $topSalesChannel = 'Sin datos';

    public string $growthUrl = '#';

    public string $leadsUrl = '#';

    public string $customersUrl = '#';

    public string $contactsUrl = '#';

    public string $marketingUrl = '#';

    public string $ordersUrl = '#';

    public string $appointmentsUrl = '#';

    public string $followersUrl = '#';

    public string $loyaltyUrl = '#';

    public string $couponsUrl = '#';

    public string $operationsUrl = '#';

    /** @var list<array{label: string, value: string, copy: string}> */
    public array $revenueMetrics = [];

    /** @var list<array{label: string, value: string, copy: string, color: string}> */
    public array $pipelineMetrics = [];

    /** @var list<array{label: string, value: string, copy: string}> */
    public array $customerSignals = [];

    /** @var list<array{title: string, copy: string, href: string}> */
    public array $growthActions = [];

    /** @var list<array{title: string, copy: string, href: string, severity: string}> */
    public array $priorityQueue = [];

    /** @var list<array{eyebrow: string, title: string, copy: string, trigger: string, channel: string, href: string, ready: bool, statusLabel: string, statusColor: string}> */
    public array $automationJourneys = [];

    /** @var array<string, mixed> */
    private array $growthProfile = [];

    public static function getNavigationGroup(): ?string
    {
        return 'Ventas';
    }

    public function mount(): void
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $tenantSlug = (string) ($tenant->slug ?? '');
        $this->tenantName = (string) ($tenant->name ?? 'Tu negocio');
        $this->isPro = (bool) $tenant->is_pro;
        $this->businessType = $tenant->business_type?->value ?? BusinessType::RETAIL->value;
        $this->businessTypeLabel = (string) (config("business-types.options.{$this->businessType}.label") ?? 'Negocio');
        $this->hasWhatsApp = filled($tenant->whatsapp_number);

        $this->growthUrl = $this->safeRoute('filament.app.pages.growth-center', ['tenant' => $tenantSlug]);
        $this->leadsUrl = $this->safeResourceUrl(LeadResource::class, ['tenant' => $tenantSlug]);
        $this->customersUrl = $this->safeResourceUrl(CustomerProfileResource::class, ['tenant' => $tenantSlug]);
        $this->contactsUrl = $this->safeResourceUrl(CustomerTouchpointResource::class, ['tenant' => $tenantSlug]);
        $this->marketingUrl = $this->safeRoute('filament.app.pages.manage-marketing', ['tenant' => $tenantSlug]);
        $this->ordersUrl = $this->safeResourceUrl(OrderResource::class, ['tenant' => $tenantSlug]);
        $this->appointmentsUrl = $this->safeResourceUrl(AppointmentResource::class, ['tenant' => $tenantSlug]);
        $this->followersUrl = $this->safeResourceUrl(FollowerResource::class, ['tenant' => $tenantSlug]);
        $this->loyaltyUrl = $this->safeResourceUrl(LoyaltyProgramResource::class, ['tenant' => $tenantSlug]);
        $this->couponsUrl = $this->safeResourceUrl(CouponResource::class, ['tenant' => $tenantSlug]);
        $this->operationsUrl = $this->safeResourceUrl(OperationalPlaybookTaskResource::class, ['tenant' => $tenantSlug]);

        $resolver = app(BusinessSegmentResolver::class);
        $this->segmentKey = $resolver->resolveForTenant($tenant);
        $internalProfile = $resolver->internalProfile($this->segmentKey, $this->businessType);
        $this->segmentLabel = (string) ($internalProfile['label'] ?? $this->businessTypeLabel);
        $this->growthProfile = app(BusinessGrowthBlueprint::class)->forSegment($this->segmentKey, $this->businessType);

        $this->headline = (string) ($this->growthProfile['headline'] ?? 'CRM y crecimiento');
        $this->copy = (string) ($this->growthProfile['copy'] ?? 'Ordena tu pipeline, entiende tu negocio y activa crecimiento con mejor disciplina.');
        $this->crmTitle = (string) ($this->growthProfile['crm_title'] ?? 'Pipeline comercial');
        $this->crmCopy = (string) ($this->growthProfile['crm_copy'] ?? 'Mide oportunidades, velocidad de respuesta y conversion.');
        $this->analyticsTitle = (string) ($this->growthProfile['analytics_title'] ?? 'Analitica PYME');
        $this->analyticsCopy = (string) ($this->growthProfile['analytics_copy'] ?? 'Lee ventas, recompra y audiencias con criterio de negocio.');
        $this->automationTitle = (string) ($this->growthProfile['automation_title'] ?? 'Automatizaciones externas');
        $this->automationCopy = (string) ($this->growthProfile['automation_copy'] ?? 'Activa automatizaciones segun tu rubro y el estado real de tu operacion.');

        $this->loadSignals($tenant);
        $this->buildRevenueMetrics();
        $this->buildPipelineMetrics();
        $this->buildCustomerSignals();
        $this->buildGrowthActions();
        $this->buildPriorityQueue();
        $this->buildAutomationJourneys();
    }

    private function loadSignals(Tenant $tenant): void
    {
        $tenantId = (string) $tenant->getKey();
        $thirtyDaysAgo = now()->subDays(30)->startOfDay();
        $oneHundredEightyDaysAgo = now()->subDays(180)->startOfDay();

        $this->ensureCustomerProfilesBootstrapped($tenantId);

        try {
            $leadBase = Lead::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId);

            $this->newLeadsCount = (clone $leadBase)->where('status', 'new')->count();
            $this->contactedLeadsCount = (clone $leadBase)->where('status', 'contacted')->count();
            $this->closedLeadsCount = (clone $leadBase)->where('status', 'closed')->count();
            $this->lostLeadsCount = (clone $leadBase)->where('status', 'lost')->count();

            $topLeadSource = (clone $leadBase)
                ->selectRaw('coalesce(source, "manual") as source_key, count(*) as total')
                ->groupBy('source_key')
                ->orderByDesc('total')
                ->first();

            if ($topLeadSource !== null) {
                $this->topLeadSource = $this->formatSourceLabel((string) $topLeadSource->source_key);
            }
        } catch (\Throwable) {
            // Leads can be optional for fresh tenants.
        }

        try {
            $orderBase = Order::query()
                ->withoutGlobalScopes()
                ->without(['items'])
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->whereNotIn('status', [Order::STATUS_CANCELLED]);

            $this->orders30d = (clone $orderBase)->count();
            $this->grossSalesOrders30d = (clone $orderBase)
                ->whereIn('status', [
                    Order::STATUS_CONFIRMED,
                    Order::STATUS_PROCESSING,
                    Order::STATUS_READY,
                    Order::STATUS_DELIVERED,
                ])
                ->count();
            $this->grossSales30d = (float) ((clone $orderBase)->sum('total') ?: 0);
            $this->averageTicket30d = $this->orders30d > 0
                ? round($this->grossSales30d / $this->orders30d, 2)
                : 0.0;

            $topSalesChannel = (clone $orderBase)
                ->selectRaw('coalesce(source, "direct") as source_key, count(*) as total')
                ->groupBy('source_key')
                ->orderByDesc('total')
                ->first();

            if ($topSalesChannel !== null) {
                $this->topSalesChannel = $this->formatOrderSourceLabel((string) $topSalesChannel->source_key);
            }

            $customerSnapshot = $this->customerSnapshotFromOrders($tenantId, $oneHundredEightyDaysAgo);
            $this->distinctCustomersCount = $customerSnapshot['distinct'];
            $this->repeatCustomerCount = $customerSnapshot['repeat'];
            $this->reactivationPoolCount = $customerSnapshot['reactivation'];
        } catch (\Throwable) {
            // Orders are optional on service-first tenants.
        }

        try {
            $appointmentBase = Appointment::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->whereNotIn('status', [Appointment::STATUS_CANCELLED]);

            $this->appointments30d = (clone $appointmentBase)->count();

            if ($this->businessType === BusinessType::SERVICE->value && $this->distinctCustomersCount === 0) {
                $customerSnapshot = $this->customerSnapshotFromAppointments($tenantId, $oneHundredEightyDaysAgo);
                $this->distinctCustomersCount = $customerSnapshot['distinct'];
                $this->repeatCustomerCount = $customerSnapshot['repeat'];
                $this->reactivationPoolCount = $customerSnapshot['reactivation'];
            }
        } catch (\Throwable) {
            // Appointments are optional on catalog-first tenants.
        }

        try {
            $analyticsBase = TenantAnalytic::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('date', '>=', $thirtyDaysAgo->toDateString());

            $this->visits30d = (int) ((clone $analyticsBase)->sum(TenantAnalytic::uniqueVisitsColumn()) ?: 0);
            $this->totalVisits30d = (int) ((clone $analyticsBase)->sum(TenantAnalytic::totalVisitsColumn()) ?: 0);
        } catch (\Throwable) {
            // Analytics sync may still be disabled.
        }

        try {
            $profilesBase = CustomerProfile::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId);

            $this->crmProfilesCount = (clone $profilesBase)->count();
            $this->reactivationPoolCount = max(
                $this->reactivationPoolCount,
                (clone $profilesBase)->where('reactivation_score', '>=', 65)->count(),
            );
            $this->repeatCustomerCount = max(
                $this->repeatCustomerCount,
                (clone $profilesBase)->whereIn('customer_segment', ['repeat', 'vip'])->count(),
            );
            $this->distinctCustomersCount = max($this->distinctCustomersCount, $this->crmProfilesCount);
        } catch (\Throwable) {
            // CRM can be empty on brand new tenants.
        }

        try {
            $this->followersCount = Follow::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->count();

            $this->waitlistCount = Waitlist::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count();
        } catch (\Throwable) {
            // Audience modules are optional.
        }

        try {
            $this->campaignsSent30d = Campaign::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('sent_at')
                ->where('sent_at', '>=', $thirtyDaysAgo)
                ->count();
        } catch (\Throwable) {
            // Campaigns are optional for free tenants.
        }

        try {
            $this->touchpoints30d = CustomerTouchpoint::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->where('status', CustomerTouchpoint::STATUS_SENT)
                ->count();
        } catch (\Throwable) {
            // Touchpoints are optional until CRM automation starts.
        }

        try {
            $this->hasLoyaltyProgram = LoyaltyProgram::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->exists();
        } catch (\Throwable) {
            $this->hasLoyaltyProgram = false;
        }

        try {
            $taskBase = OperationalPlaybookTask::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', [
                    OperationalPlaybookTask::STATUS_OPEN,
                    OperationalPlaybookTask::STATUS_IN_PROGRESS,
                ]);

            $this->openPlaybooksCount = (clone $taskBase)->count();
            $this->overduePlaybooksCount = (clone $taskBase)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count();
        } catch (\Throwable) {
            // No active operational queue yet.
        }
    }

    private function buildRevenueMetrics(): void
    {
        $leadCaptureRate = $this->visits30d > 0
            ? round((($this->newLeadsCount + $this->contactedLeadsCount + $this->closedLeadsCount) / $this->visits30d) * 100, 1)
            : 0.0;

        $repeatRate = $this->distinctCustomersCount > 0
            ? round(($this->repeatCustomerCount / $this->distinctCustomersCount) * 100, 1)
            : 0.0;

        $this->revenueMetrics = [
            $this->metric('Ventas 30 dias', $this->formatCurrency($this->grossSales30d), "{$this->orders30d} operaciones registradas en el periodo."),
            $this->metric('Ticket promedio', $this->formatCurrency($this->averageTicket30d), 'Una lectura rapida para ver si estas vendiendo mejor o solo mas.'),
            $this->metric('Captura de leads', $leadCaptureRate.'%', $this->visits30d > 0 ? "Con {$this->visits30d} visitas unicas recientes." : 'Todavia no hay trafico sincronizado suficiente.'),
            $this->metric('Clientes que vuelven', $repeatRate.'%', $this->repeatCustomerCount > 0 ? "{$this->repeatCustomerCount} clientes ya repitieron." : 'Aun no tienes recompra identificada.'),
        ];
    }

    private function buildPipelineMetrics(): void
    {
        $leadTotal = $this->newLeadsCount + $this->contactedLeadsCount + $this->closedLeadsCount + $this->lostLeadsCount;
        $closeRate = $leadTotal > 0 ? round(($this->closedLeadsCount / $leadTotal) * 100, 1) : 0.0;

        $this->pipelineMetrics = [
            $this->pipelineMetric('Nuevos', (string) $this->newLeadsCount, 'Oportunidades esperando primera respuesta.', 'warning'),
            $this->pipelineMetric('Contactados', (string) $this->contactedLeadsCount, 'Prospectos en seguimiento activo.', 'info'),
            $this->pipelineMetric('Cerrados', (string) $this->closedLeadsCount, 'Conversiones que ya movieron negocio.', 'success'),
            $this->pipelineMetric('Cierre', $closeRate.'%', "Origen mas fuerte: {$this->topLeadSource}.", $closeRate >= 25 ? 'success' : 'gray'),
        ];
    }

    private function buildCustomerSignals(): void
    {
        $this->customerSignals = [
            $this->metric('Clientes CRM', (string) $this->crmProfilesCount, $this->crmProfilesCount > 0 ? 'Ya tienes fichas listas para seguimiento y reactivacion.' : 'Todavia no hay clientes consolidados en CRM.'),
            $this->metric('Seguidores', (string) $this->followersCount, $this->followersCount > 0 ? 'Tu audiencia ya existe y se puede nutrir.' : 'Todavia no hay audiencia guardada en PRO.'),
            $this->metric('Waitlist activa', (string) $this->waitlistCount, $this->waitlistCount > 0 ? 'Hay demanda esperando huecos u oferta.' : 'No hay espera activa en este momento.'),
            $this->metric('Campanas 30 dias', (string) $this->campaignsSent30d, $this->campaignsSent30d > 0 ? 'Ya tienes empuje comercial reciente.' : 'Aun no has activado una campana reciente.'),
            $this->metric('Contactos enviados', (string) $this->touchpoints30d, $this->touchpoints30d > 0 ? 'Tu CRM ya esta ejecutando seguimiento auditable.' : 'Todavia no has movido seguimientos desde el CRM.'),
            $this->metric('Reactivar', (string) $this->reactivationPoolCount, $this->reactivationPoolCount > 0 ? 'Clientes con senales para volver a tocar.' : 'La base reciente no muestra fuga grande todavia.'),
        ];
    }

    private function buildGrowthActions(): void
    {
        $actions = [
            $this->action('Abrir clientes', 'Trabaja fichas, notas, segmentos y reactivacion desde un CRM unificado.', $this->customersUrl),
            $this->action('Ver contactos', 'Audita correos, seguimientos y entregas del negocio.', $this->contactsUrl),
            $this->action('Abrir prospectos', 'Ordena pipeline, fuentes y estado de cierre.', $this->leadsUrl),
            $this->action('Ir a marketing', 'Ajusta CTA, QR, WhatsApp y campanas.', $this->marketingUrl),
            $this->action('Ver centro operativo', 'Convierte automatizaciones en cola con SLA.', $this->operationsUrl),
        ];

        if ($this->businessType === BusinessType::SERVICE->value) {
            $actions[] = $this->action('Ver agenda', 'Usa citas y asistencia como motor de continuidad.', $this->appointmentsUrl);
        } else {
            $actions[] = $this->action('Ver pedidos', 'Conecta conversion con ticket y recompra.', $this->ordersUrl);
        }

        if ($this->isPro) {
            $actions[] = $this->action('Audiencia y fidelidad', 'Mueve seguidores, loyalty y reactivacion con mas criterio.', $this->hasLoyaltyProgram ? $this->loyaltyUrl : $this->followersUrl);
        }

        $this->growthActions = array_slice($actions, 0, 6);
    }

    private function buildPriorityQueue(): void
    {
        $queue = [];

        if ($this->newLeadsCount > $this->contactedLeadsCount) {
            $queue[] = $this->priority('Responde prospectos nuevos', 'Tienes mas leads esperando primer toque que leads ya trabajados.', $this->leadsUrl, 'danger');
        }

        if ($this->visits30d >= 20 && ($this->newLeadsCount + $this->contactedLeadsCount + $this->closedLeadsCount) === 0) {
            $queue[] = $this->priority('Cierra mejor tu captura', 'Ya hay trafico, pero casi no estas convirtiendo visitas en conversaciones.', $this->marketingUrl, 'warning');
        }

        if ($this->repeatCustomerCount > 0 && ! $this->hasLoyaltyProgram && $this->isPro) {
            $queue[] = $this->priority('Activa fidelidad', 'Ya existe recompra: conviene convertirla en programa y no dejarla al azar.', $this->loyaltyUrl, 'warning');
        }

        if ($this->campaignsSent30d === 0 && $this->isPro && ($this->followersCount > 0 || $this->closedLeadsCount > 0)) {
            $queue[] = $this->priority('Lanza una campana', 'Tu base ya aguanta un empuje comercial y todavia no lo estas usando.', $this->marketingUrl, 'info');
        }

        if ($this->overduePlaybooksCount > 0) {
            $queue[] = $this->priority('Atiende playbooks vencidos', 'Tus automatizaciones ya generaron cola atrasada y conviene moverla hoy.', $this->operationsUrl, 'danger');
        }

        if ($this->waitlistCount > 0 && $this->businessType === BusinessType::SERVICE->value) {
            $queue[] = $this->priority('Convierte tu waitlist', 'Hay clientes esperando espacio. Esto es dinero cerca de cerrar.', $this->appointmentsUrl, 'info');
        }

        $this->priorityQueue = array_slice($queue, 0, 4);
    }

    private function buildAutomationJourneys(): void
    {
        $journeys = collect((array) ($this->growthProfile['journeys'] ?? []))
            ->map(function (array $journey): array {
                $readiness = $this->resolveAutomationReadiness((string) ($journey['state'] ?? ''));

                return [
                    'eyebrow' => (string) ($journey['eyebrow'] ?? 'Automatizacion'),
                    'title' => (string) ($journey['title'] ?? 'Journey'),
                    'copy' => (string) ($journey['copy'] ?? ''),
                    'trigger' => (string) ($journey['trigger'] ?? ''),
                    'channel' => (string) ($journey['channel'] ?? 'Panel'),
                    'href' => $this->resolveGrowthHref((string) ($journey['route_key'] ?? 'operations')),
                    'ready' => $readiness['ready'],
                    'statusLabel' => $readiness['label'],
                    'statusColor' => $readiness['color'],
                ];
            })
            ->filter(static fn (array $journey): bool => $journey['title'] !== '')
            ->values()
            ->all();

        $this->automationJourneys = $journeys;
    }

    /**
     * @return array{distinct: int, repeat: int, reactivation: int}
     */
    private function customerSnapshotFromOrders(string $tenantId, Carbon $since): array
    {
        $customerKey = "coalesce(nullif(lower(customer_email), ''), concat('phone:', nullif(customer_phone, '')), concat('guest:', id))";

        /** @var Collection<int, object> $rows */
        $rows = Order::query()
            ->withoutGlobalScopes()
            ->without(['items'])
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->whereNotIn('status', [Order::STATUS_CANCELLED])
            ->selectRaw("{$customerKey} as customer_key, count(*) as total_orders, max(created_at) as last_seen_at")
            ->groupByRaw($customerKey)
            ->get();

        return $this->aggregateCustomerSnapshot($rows);
    }

    /**
     * @return array{distinct: int, repeat: int, reactivation: int}
     */
    private function customerSnapshotFromAppointments(string $tenantId, Carbon $since): array
    {
        $customerKey = "coalesce(nullif(lower(customer_email), ''), concat('phone:', nullif(customer_phone, '')), concat('guest:', id))";

        /** @var Collection<int, object> $rows */
        $rows = Appointment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
            ->selectRaw("{$customerKey} as customer_key, count(*) as total_orders, max(created_at) as last_seen_at")
            ->groupByRaw($customerKey)
            ->get();

        return $this->aggregateCustomerSnapshot($rows);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{distinct: int, repeat: int, reactivation: int}
     */
    private function aggregateCustomerSnapshot(Collection $rows): array
    {
        $distinct = $rows->count();
        $repeat = $rows->filter(static fn (object $row): bool => (int) ($row->total_orders ?? 0) >= 2)->count();
        $reactivation = $rows->filter(function (object $row): bool {
            $lastSeen = $row->last_seen_at ?? null;

            if ($lastSeen === null) {
                return false;
            }

            return Carbon::parse((string) $lastSeen)->lt(now()->subDays(30));
        })->count();

        return [
            'distinct' => $distinct,
            'repeat' => $repeat,
            'reactivation' => $reactivation,
        ];
    }

    /**
     * @return array{ready: bool, label: string, color: string}
     */
    private function resolveAutomationReadiness(string $state): array
    {
        return match ($state) {
            'whatsapp_channel' => $this->readiness($this->hasWhatsApp, 'Canal listo', 'Configura WhatsApp'),
            'whatsapp_and_leads' => $this->readiness($this->hasWhatsApp && ($this->newLeadsCount + $this->contactedLeadsCount + $this->closedLeadsCount) > 0, 'Lista para operar', 'Necesita canal y pipeline'),
            'appointments_ready' => $this->readiness($this->appointments30d > 0 || $this->businessType === BusinessType::SERVICE->value, 'Agenda lista', 'Activa agenda o citas'),
            'mixed_activity' => $this->readiness(($this->orders30d + $this->appointments30d) > 0, 'Actividad lista', 'Necesita pedidos o citas'),
            'audience_ready' => $this->readiness(($this->followersCount + $this->newLeadsCount + $this->contactedLeadsCount + $this->closedLeadsCount) > 0, 'Audiencia lista', 'Necesita audiencia'),
            'orders_or_audience' => $this->readiness($this->orders30d > 0 || $this->followersCount > 0, 'Base lista', 'Necesita pedidos o audiencia'),
            'promo_growth' => $this->readiness($this->isPro, 'Lista para promo', 'Requiere PRO'),
            'loyalty_growth' => $this->readiness($this->isPro && ($this->hasLoyaltyProgram || $this->repeatCustomerCount > 0), $this->hasLoyaltyProgram ? 'Loyalty activo' : 'Base lista para loyalty', 'Requiere PRO o recompra'),
            default => $this->readiness(true, 'Disponible', 'Disponible'),
        };
    }

    /**
     * @return array{ready: bool, label: string, color: string}
     */
    private function readiness(bool $ready, string $readyLabel, string $pendingLabel): array
    {
        return [
            'ready' => $ready,
            'label' => $ready ? $readyLabel : $pendingLabel,
            'color' => $ready ? 'success' : 'warning',
        ];
    }

    private function resolveGrowthHref(string $routeKey): string
    {
        return match ($routeKey) {
            'customers' => $this->customersUrl,
            'leads' => $this->leadsUrl,
            'marketing' => $this->marketingUrl,
            'orders' => $this->ordersUrl,
            'appointments' => $this->appointmentsUrl,
            'followers' => $this->followersUrl,
            'loyalty' => $this->loyaltyUrl,
            'coupons' => $this->couponsUrl,
            default => $this->operationsUrl,
        };
    }

    private function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
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

    /**
     * @return array{label: string, value: string, copy: string}
     */
    private function metric(string $label, string $value, string $copy): array
    {
        return compact('label', 'value', 'copy');
    }

    /**
     * @return array{label: string, value: string, copy: string, color: string}
     */
    private function pipelineMetric(string $label, string $value, string $copy, string $color): array
    {
        return compact('label', 'value', 'copy', 'color');
    }

    /**
     * @return array{title: string, copy: string, href: string}
     */
    private function action(string $title, string $copy, string $href): array
    {
        return compact('title', 'copy', 'href');
    }

    /**
     * @return array{title: string, copy: string, href: string, severity: string}
     */
    private function priority(string $title, string $copy, string $href, string $severity): array
    {
        return compact('title', 'copy', 'href', 'severity');
    }

    private function formatCurrency(float $value): string
    {
        return 'L. '.number_format($value, 2);
    }

    private function formatSourceLabel(string $source): string
    {
        return match ($source) {
            'whatsapp_click' => 'WhatsApp',
            'catalog' => 'Catalogo',
            'website' => 'Sitio web',
            'manual' => 'Manual',
            default => ucfirst(str_replace('_', ' ', $source)),
        };
    }

    private function formatOrderSourceLabel(string $source): string
    {
        return match ($source) {
            'web' => 'Web',
            'whatsapp' => 'WhatsApp',
            'direct' => 'Directo',
            default => ucfirst(str_replace('_', ' ', $source)),
        };
    }

    private function ensureCustomerProfilesBootstrapped(string $tenantId): void
    {
        $profilesExist = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->exists();

        if ($profilesExist) {
            return;
        }

        $hasCustomerSignals = Order::query()
            ->withoutGlobalScopes()
            ->without(['items'])
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query->whereNotNull('customer_email')->orWhereNotNull('customer_phone');
            })
            ->exists()
            || Appointment::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($query): void {
                    $query->whereNotNull('customer_email')->orWhereNotNull('customer_phone');
                })
                ->exists()
            || Lead::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($query): void {
                    $query->whereNotNull('email')->orWhereNotNull('phone');
                })
                ->exists();

        if ($hasCustomerSignals) {
            app(CustomerProfileManager::class)->syncTenant($tenantId);
        }
    }
}
