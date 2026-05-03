<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Application\TenantGovernance\Handlers\GetTenantGrowthSignalsHandler;
use App\Application\TenantGovernance\Handlers\GetTenantJourneyAnalyticsHandler;
use App\Application\TenantGovernance\Handlers\GetTenantOperationalComparisonsHandler;
use App\Application\TenantGovernance\Queries\GetTenantGrowthSignalsQuery;
use App\Application\TenantGovernance\Queries\GetTenantJourneyAnalyticsQuery;
use App\Application\TenantGovernance\Queries\GetTenantOperationalComparisonsQuery;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Domain\TenantGovernance\Services\BusinessGrowthBlueprint;
use App\Domain\TenantGovernance\Services\BusinessSegmentResolver;
use App\Filament\App\Resources\AppointmentResource;
use App\Filament\App\Resources\CampaignResource;
use App\Filament\App\Resources\CouponResource;
use App\Filament\App\Resources\CrmDealResource;
use App\Filament\App\Resources\CustomerProfileResource;
use App\Filament\App\Resources\CustomerTouchpointResource;
use App\Filament\App\Resources\FollowerResource;
use App\Filament\App\Resources\Leads\LeadResource;
use App\Filament\App\Resources\LoyaltyProgramResource;
use App\Filament\App\Resources\OperationalPlaybookTaskResource;
use App\Filament\App\Resources\OrderResource;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Pages\Page;

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

    public string $crmTitle = 'Seguimiento comercial';

    public string $crmCopy = '';

    public string $analyticsTitle = 'Analitica PYME';

    public string $analyticsCopy = '';

    public string $automationTitle = 'Automatizaciones externas';

    public string $automationCopy = '';

    public bool $isPro = false;

    public bool $hasMarketingSuite = false;

    public bool $hasCampaignFeature = false;

    public bool $hasLoyaltyFeature = false;

    public bool $canUpgradeToPro = false;

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

    public int $touchpointsDelivered30d = 0;

    public int $touchpointsOpened30d = 0;

    public int $touchpointsBounced30d = 0;

    public int $dueTouchpointRetriesCount = 0;

    public int $scheduledTouchpointRetriesCount = 0;

    public int $whatsAppTouchpoints30d = 0;

    public int $whatsAppReplies30d = 0;

    public int $campaignsSent30d = 0;

    public int $openPlaybooksCount = 0;

    public int $overduePlaybooksCount = 0;

    public string $topLeadSource = 'Sin datos';

    public string $topSalesChannel = 'Sin datos';

    public string $topOfferLabel = 'Sin datos';

    public string $topOfferCopy = 'Todavía no hay suficiente actividad reciente para destacar una oferta.';

    public int $vipCustomersCount = 0;

    public int $dormantCustomersCount = 0;

    public int $notes7dCount = 0;

    public int $openRepliesCount = 0;

    public int $reactivatedCustomers30d = 0;

    public int $loyaltyReadyCustomersCount = 0;

    public int $redeemableCustomersCount = 0;

    public int $breachedRepliesCount = 0;

    public int $resolvedReplies30d = 0;

    public float $recurrenceRate = 0.0;

    public float $deliveryRate30d = 0.0;

    public float $openRate30d = 0.0;

    public float $optInCoverageRate = 0.0;

    public float $averageInitialLtv = 0.0;

    public float $averageFirstResponseMinutes = 0.0;

    public float $responseRate30d = 0.0;

    public float $repeatRevenue30d = 0.0;

    public string $topAcquisitionChannel = 'Sin datos';

    public string $topCustomerSegment = 'Sin datos';

    public string $topLtvSegment = 'Sin datos';

    public string $topLtvChannel = 'Sin datos';

    public string $topLoyaltyChannel = 'Sin datos';

    public string $topTemplateLabel = 'Sin datos';

    public string $topClosingChannel = 'Sin datos';

    public string $topReactivationSegment = 'Sin datos';

    public int $scheduledCampaignsCount = 0;

    public int $recurringCampaignsCount = 0;

    public int $rewardCampaignsCount = 0;

    public int $loyaltyRedeems30d = 0;

    public int $currentCohortCustomersCount = 0;

    public int $previousCohortCustomersCount = 0;

    public float $cohortRepeatRate = 0.0;

    public float $cohortLtv = 0.0;

    public int $openCustomerTasksCount = 0;

    public int $overdueCustomerTasksCount = 0;

    public int $journeysReadyCount = 0;

    public int $stalledJourneysCount = 0;

    public int $campaignReadyCustomersCount = 0;

    public int $assignedCustomersCount = 0;

    public int $locatedCustomersCount = 0;

    public string $growthUrl = '';

    public string $leadsUrl = '';

    public string $customersUrl = '';

    public string $contactsUrl = '';

    public string $inboxUrl = '';

    public string $marketingUrl = '';

    public string $campaignsUrl = '';

    public string $ordersUrl = '';

    public string $appointmentsUrl = '';

    public string $followersUrl = '';

    public string $loyaltyUrl = '';

    public string $couponsUrl = '';

    public string $operationsUrl = '';

    public string $dealsUrl = '';
    public bool $canAccessCrmDeals = false;

    /** @var list<array{label: string, value: string, copy: string}> */
    public array $revenueMetrics = [];

    /** @var list<array{label: string, value: string, copy: string, color: string}> */
    public array $pipelineMetrics = [];

    /** @var list<array{label: string, value: string, copy: string}> */
    public array $customerSignals = [];

    /** @var list<array{label: string, value: string, copy: string}> */
    public array $engagementMetrics = [];

    /** @var list<array{label: string, value: string, copy: string}> */
    public array $cohortMetrics = [];

    /** @var list<array{name: string, revenue: string, customers: int, openTasks: int, overdueTasks: int, repeatRate: string, context: string}> */
    public array $ownerComparisons = [];

    /** @var list<array{name: string, revenue: string, customers: int, openTasks: int, overdueTasks: int, repeatRate: string, context: string}> */
    public array $locationComparisons = [];

    /** @var list<array{label:string,channel:string,sent:int,replied:int,conversions:int,revenue:string,replyRate:string,closeRate:string}> */
    public array $templatePerformance = [];

    /** @var list<array{label:string,branch:string,lane:string,sent:int,replied:int,conversions:int,revenue:string,replyRate:string,closeRate:string}> */
    public array $sequencePerformance = [];

    /** @var list<array{label:string,revenue:string,replies:int,clicks:int,conversions:string}> */
    public array $sequenceAttribution = [];

    /** @var list<array{label:string,revenue:string,conversations:int,owners:string,channels:string}> */
    public array $conversationPathAttribution = [];

    /** @var list<array{name:string,revenue:string,replies:int,clicks:int,conversions:string,context:string}> */
    public array $ownerSequenceAttribution = [];

    /** @var list<array{name:string,revenue:string,replies:int,clicks:int,conversions:string,context:string}> */
    public array $locationSequenceAttribution = [];

    /** @var list<array{name:string,revenue:string,replies:int,clicks:int,conversions:string,context:string}> */
    public array $channelSequenceAttribution = [];

    /** @var list<array{label:string,revenue:string,purchaseClosures:int,noShowRecovered:int,rewardRepurchases:int,upgradeAssists:int}> */
    public array $sequenceOutcomeAttribution = [];

    public float $upgradeAssistedRevenue30d = 0.0;

    /** @var list<array{name:string,total:int,closed:int,conversions:int,revenue:string,waiting:int,breached:int,avgFirstResponse:string,conversionRate:string}> */
    public array $ownerConversationPerformance = [];

    /** @var list<array{name:string,total:int,closed:int,conversions:int,revenue:string,waiting:int,breached:int,avgFirstResponse:string,conversionRate:string}> */
    public array $locationConversationPerformance = [];

    /** @var list<array{title: string, copy: string, href: string, enabled: bool, reason: string}> */
    public array $growthActions = [];

    /** @var list<array{title: string, copy: string, href: string, severity: string}> */
    public array $priorityQueue = [];

    /** @var list<array{eyebrow: string, title: string, copy: string, trigger: string, channel: string, href: string, ready: bool, statusLabel: string, statusColor: string}> */
    public array $automationJourneys = [];

    /** @var list<array{title: string, cadence: string, trigger: string, channel: string, copy: string, href: string, ready: bool, statusLabel: string, statusColor: string}> */
    public array $cadencePlaybooks = [];

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
        $this->hasMarketingSuite = $tenant->hasFeature(TenantFeature::MARKETING_SUITE);
        $this->hasCampaignFeature = $tenant->hasFeature(TenantFeature::MARKETING_CAMPAIGNS);
        $this->hasLoyaltyFeature = $tenant->hasFeature(TenantFeature::LOYALTY_PROGRAM);
        $this->canUpgradeToPro = $tenant->hasFeature(TenantFeature::UPGRADE_PROMPT);
        $this->isPro = ! $this->canUpgradeToPro;
        $this->businessType = $tenant->business_type->value;
        $this->businessTypeLabel = (string) (config("business-types.options.{$this->businessType}.label") ?? 'Negocio');
        $this->hasWhatsApp = filled($tenant->whatsapp_number);

        $this->growthUrl = $this->safeRoute('filament.app.pages.growth-center', ['tenant' => $tenantSlug]);
        $this->leadsUrl = $this->safeResourceUrl(LeadResource::class, ['tenant' => $tenantSlug]);
        $this->customersUrl = $this->safeResourceUrl(CustomerProfileResource::class, ['tenant' => $tenantSlug]);
        $this->contactsUrl = $this->safeResourceUrl(CustomerTouchpointResource::class, ['tenant' => $tenantSlug]);
        $this->inboxUrl = $this->safePageUrl(CustomerInbox::class, ['tenant' => $tenantSlug]);
        $this->marketingUrl = $this->safePageUrl(ManageMarketing::class, ['tenant' => $tenantSlug]);
        $this->campaignsUrl = $this->safeResourceUrl(CampaignResource::class, ['tenant' => $tenantSlug]);
        $this->ordersUrl = $this->safeResourceUrl(OrderResource::class, ['tenant' => $tenantSlug]);
        $this->appointmentsUrl = $this->safeResourceUrl(AppointmentResource::class, ['tenant' => $tenantSlug]);
        $this->followersUrl = $this->safeResourceUrl(FollowerResource::class, ['tenant' => $tenantSlug]);
        $this->loyaltyUrl = $this->safeResourceUrl(LoyaltyProgramResource::class, ['tenant' => $tenantSlug]);
        $this->couponsUrl = $this->safeResourceUrl(CouponResource::class, ['tenant' => $tenantSlug]);
        $this->operationsUrl = $this->safeResourceUrl(OperationalPlaybookTaskResource::class, ['tenant' => $tenantSlug]);
        $this->dealsUrl = $this->safeResourceUrl(CrmDealResource::class, ['tenant' => $tenantSlug]);
        $this->canAccessCrmDeals = $this->dealsUrl !== '';

        $resolver = app(BusinessSegmentResolver::class);
        $this->segmentKey = $resolver->resolveForTenant($tenant);
        $internalProfile = $resolver->internalProfile($this->segmentKey, $this->businessType);
        $this->segmentLabel = (string) ($internalProfile['label'] ?? $this->businessTypeLabel);
        $this->growthProfile = app(BusinessGrowthBlueprint::class)->forTenant($tenant);

        $this->headline = (string) ($this->growthProfile['headline'] ?? 'CRM y crecimiento');
        $this->copy = (string) ($this->growthProfile['copy'] ?? 'Ordena prospectos, clientes y seguimientos con mejor disciplina.');
        $this->crmTitle = (string) ($this->growthProfile['crm_title'] ?? 'Seguimiento comercial');
        $this->crmCopy = (string) ($this->growthProfile['crm_copy'] ?? 'Mide prospectos y seguimiento sin prometer un CRM completo.');
        if (! $this->canAccessCrmDeals) {
            $this->crmCopy = $this->canUpgradeToPro
                ? 'Tu plan actual no incluye oportunidades CRM. Puedes operar prospectos y clientes; deals requiere upgrade y permiso.'
                : 'Tu rol no tiene acceso a oportunidades CRM. Mantienes visibilidad de prospectos y clientes con permisos actuales.';
        }
        $this->analyticsTitle = (string) ($this->growthProfile['analytics_title'] ?? 'Analitica PYME');
        $this->analyticsCopy = (string) ($this->growthProfile['analytics_copy'] ?? 'Lee ventas, recompra y audiencias con criterio de negocio.');
        $this->automationTitle = (string) ($this->growthProfile['automation_title'] ?? 'Automatizaciones externas');
        $this->automationCopy = (string) ($this->growthProfile['automation_copy'] ?? 'Activa automatizaciones según tu rubro y el estado real de tu operación.');

        $this->loadSignals($tenant);
        $this->buildRevenueMetrics();
        $this->buildPipelineMetrics();
        $this->buildCustomerSignals();
        $this->buildEngagementMetrics();
        $this->buildCohortMetrics();
        $this->buildGrowthActions();
        $this->buildPriorityQueue();
        $this->buildAutomationJourneys();
        $this->buildCadencePlaybooks();
        $this->buildOperationalComparisons();
        $this->buildConversationPerformanceTables();
    }

    private function loadSignals(Tenant $tenant): void
    {
        /** @var GetTenantGrowthSignalsHandler $handler */
        $handler = app(GetTenantGrowthSignalsHandler::class);
        $summary = $handler->handle(new GetTenantGrowthSignalsQuery(
            tenantId: (string) $tenant->getKey(),
            businessType: $this->businessType,
        ));

        foreach ($summary as $property => $value) {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }
    }

    private function buildRevenueMetrics(): void
    {
        $leadCaptureRate = $this->visits30d > 0
            ? round((($this->newLeadsCount + $this->contactedLeadsCount + $this->closedLeadsCount) / $this->visits30d) * 100, 1)
            : 0.0;

        $this->revenueMetrics = [
            $this->metric('Ventas 30 días', $this->formatCurrency($this->grossSales30d), "{$this->orders30d} operaciones registradas en el periodo."),
            $this->metric('Ticket promedio', $this->formatCurrency($this->averageTicket30d), 'Una lectura rapida para ver si estas vendiendo mejor o solo mas.'),
            $this->metric('Ingreso recurrente', $this->formatCurrency($this->repeatRevenue30d), $this->repeatRevenue30d > 0 ? 'Caja reciente movida por clientes que ya regresaron al menos una vez.' : 'Todavía no hay ingreso recurrente fuerte en el período.'),
            $this->metric('Captura de leads', $leadCaptureRate.'%', $this->visits30d > 0 ? "Con {$this->visits30d} visitas únicas recientes." : 'Todavía no hay tráfico sincronizado suficiente.'),
            $this->metric('Clientes que vuelven', $this->recurrenceRate.'%', $this->repeatCustomerCount > 0 ? "{$this->repeatCustomerCount} clientes ya repitieron." : 'Aun no tienes recompra identificada.'),
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
            $this->pipelineMetric('Cierre', $closeRate.'%', "Origen más fuerte: {$this->topLeadSource}.", $closeRate >= 25 ? 'success' : 'gray'),
        ];
    }

    private function buildCustomerSignals(): void
    {
        $this->customerSignals = [
            $this->metric('Clientes CRM', (string) $this->crmProfilesCount, $this->crmProfilesCount > 0 ? 'Ya tienes fichas listas para seguimiento y reactivación.' : 'Todavía no hay clientes consolidados en CRM.'),
            $this->metric('Seguidores', (string) $this->followersCount, $this->followersCount > 0 ? 'Tu audiencia ya existe y se puede nutrir.' : 'Todavía no hay audiencia guardada en PRO.'),
            $this->metric('Waitlist activa', (string) $this->waitlistCount, $this->waitlistCount > 0 ? 'Hay demanda esperando huecos u oferta.' : 'No hay espera activa en este momento.'),
            $this->metric('Campañas 30 días', (string) $this->campaignsSent30d, $this->campaignsSent30d > 0 ? 'Ya tienes empuje comercial reciente.' : 'Aún no has activado una campaña reciente.'),
            $this->metric('Contactos enviados', (string) $this->touchpoints30d, $this->touchpoints30d > 0 ? 'Tu CRM ya está ejecutando seguimiento auditable.' : 'Todavía no has movido seguimientos desde el CRM.'),
            $this->metric('Retries pendientes', (string) $this->dueTouchpointRetriesCount, $this->dueTouchpointRetriesCount > 0 ? 'Hay contactos listos para reintento automatico o manual.' : 'La cola de retries esta sana en este momento.'),
            $this->metric('Bandeja abierta', (string) $this->openRepliesCount, $this->openRepliesCount > 0 ? 'Conversaciones esperando respuesta o cierre humano.' : 'No hay replies pendientes en la cola activa.'),
            $this->metric('Journeys activos', (string) $this->journeysReadyCount, $this->journeysReadyCount > 0 ? 'Secuencias de retención ya vivas dentro del CRM.' : 'Todavía no hay secuencias activas listas para operar.'),
            $this->metric('Journeys en rescate', (string) $this->stalledJourneysCount, $this->stalledJourneysCount > 0 ? 'Secuencias esperando un rescate humano para no perder continuidad.' : 'No hay secuencias trabadas en este momento.'),
            $this->metric('Canjeables hoy', (string) $this->redeemableCustomersCount, $this->redeemableCustomersCount > 0 ? 'Clientes que ya pueden canjear una recompensa visible.' : 'Todavía no hay clientes listos para un canje inmediato.'),
            $this->metric('Listos para campaña', (string) $this->campaignReadyCustomersCount, $this->campaignReadyCustomersCount > 0 ? 'Base con opt-in y canal útil para una campaña segmentada.' : 'Todavía falta base con permiso claro para campañas CRM.'),
            $this->metric('Con owner', (string) $this->assignedCustomersCount, $this->assignedCustomersCount > 0 ? 'Clientes ya asignados a alguien del equipo para continuidad.' : 'Todavía no has repartido cartera entre el equipo.'),
            $this->metric('Con sede', (string) $this->locatedCustomersCount, $this->locatedCustomersCount > 0 ? 'Clientes ya conectados a una sucursal o punto de atención.' : 'Todavía no hay base suficiente etiquetada por sede.'),
            $this->metric('Reactivar', (string) $this->reactivationPoolCount, $this->reactivationPoolCount > 0 ? 'Clientes con señales para volver a tocar.' : 'La base reciente no muestra fuga grande todavía.'),
            $this->metric('Reactivados 30 días', (string) $this->reactivatedCustomers30d, $this->reactivatedCustomers30d > 0 ? 'Clientes que ya respondieron a una nueva activación reciente.' : 'Aún no tienes reactivaciones recientes registradas.'),
            $this->metric('Segmento que reactiva', $this->topReactivationSegment, $this->reactivatedCustomers30d > 0 ? 'El segmento que más está volviendo después de tus journeys y follow-up.' : 'Todavía no hay suficiente reactivación para detectar un segmento ganador.'),
            $this->metric('Tier con fidelidad', (string) $this->loyaltyReadyCustomersCount, $this->loyaltyReadyCustomersCount > 0 ? 'Clientes listos para beneficios, bundles o continuidad.' : 'Todavía no hay base suficiente para fidelidad segmentada.'),
        ];
    }

    private function buildEngagementMetrics(): void
    {
        $this->engagementMetrics = [
            $this->metric('Oferta top', $this->topOfferLabel, $this->topOfferCopy),
            $this->metric('LTV inicial', $this->formatCurrency($this->averageInitialLtv), $this->averageInitialLtv > 0 ? 'Promedio del valor capturado en los primeros 30 días desde la primera conversión.' : 'Aún no hay conversión suficiente para estimar LTV inicial.'),
            $this->metric('Recurrencia', $this->recurrenceRate.'%', $this->repeatCustomerCount > 0 ? "{$this->repeatCustomerCount} clientes ya compraron o reservaron más de una vez." : 'Todavía no se ve repetición fuerte en la base.'),
            $this->metric('Canal dominante', $this->topAcquisitionChannel, 'La entrada que mas clientes reales esta trayendo al CRM hoy.'),
            $this->metric('Segmento dominante', $this->topCustomerSegment, 'El grupo que hoy concentra mas clientes dentro del CRM.'),
            $this->metric('Segmento con mejor LTV', $this->topLtvSegment, 'El cluster que hoy deja mas valor acumulado por cliente.'),
            $this->metric('Canal con mejor LTV', $this->topLtvChannel, 'La entrada que esta trayendo clientes con mejor vida util.'),
            $this->metric('Primera respuesta', $this->averageFirstResponseMinutes > 0 ? $this->averageFirstResponseMinutes.' min' : 'Sin datos', $this->averageFirstResponseMinutes > 0 ? 'Tiempo medio entre reply del cliente y primer movimiento del equipo.' : 'Todavía no hay conversaciones respondidas para medir velocidad.'),
            $this->metric('Tasa de respuesta', $this->responseRate30d.'%', $this->responseRate30d > 0 ? 'Porcentaje de conversaciones recientes donde el equipo alcanzó primera respuesta.' : 'Todavía no hay suficiente volumen conversacional para medir respuesta.'),
            $this->metric('SLA vencido', (string) $this->breachedRepliesCount, $this->breachedRepliesCount > 0 ? 'Casos de conversacion ya fuera de tiempo comercial.' : 'No hay replies vencidos en este momento.'),
            $this->metric('Entrega de email', $this->deliveryRate30d.'%', $this->touchpoints30d > 0 ? "{$this->touchpointsDelivered30d} contactos llegaron a bandeja de entrada." : 'Aun no hay seguimiento suficiente para medir entrega.'),
            $this->metric('Apertura de email', $this->openRate30d.'%', $this->touchpointsOpened30d > 0 ? "{$this->touchpointsOpened30d} contactos ya abrieron tus mensajes." : 'Todavía no hay aperturas registradas por proveedor.'),
            $this->metric('Respuestas por WhatsApp', (string) $this->whatsAppReplies30d, $this->whatsAppTouchpoints30d > 0 ? "{$this->whatsAppReplies30d} conversaciones devolvieron respuesta reciente." : 'Todavía no hay conversaciones de WhatsApp suficientes para medir respuesta.'),
            $this->metric('Plantilla que más convierte', $this->topTemplateLabel, $this->touchpoints30d > 0 ? 'Es la plantilla con mejor señal reciente de respuesta o cierre dentro del CRM.' : 'Todavía no hay suficiente volumen para detectar una plantilla ganadora.'),
            $this->metric('Canal que mas cierra', $this->topClosingChannel, $this->resolvedReplies30d > 0 ? 'Es el canal que mas conversaciones esta cerrando en el periodo reciente.' : 'Aun no hay suficientes cierres recientes para detectar un canal dominante.'),
            $this->metric('Revenue con uplift PRO', $this->formatCurrency($this->upgradeAssistedRevenue30d), $this->upgradeAssistedRevenue30d > 0 ? "Revenue atribuido a secuencias apoyadas por el upgrade reciente del negocio en {$this->segmentLabel}." : 'Todavía no se detecta uplift de revenue ligado al upgrade reciente del negocio.'),
            $this->metric('Retries programados', (string) $this->scheduledTouchpointRetriesCount, $this->scheduledTouchpointRetriesCount > 0 ? 'La cola ya tiene reintentos futuros listos para ejecutar.' : 'No hay reintentos en espera ahora mismo.'),
        ];
    }

    private function buildCohortMetrics(): void
    {
        $this->cohortMetrics = [
            $this->metric('Cohorte actual', (string) $this->currentCohortCustomersCount, $this->currentCohortCustomersCount > 0 ? 'Clientes captados en el mes actual que ya entraron al CRM.' : 'Todavía no hay clientes nuevos en la cohorte del mes.'),
            $this->metric('Cohorte previa', (string) $this->previousCohortCustomersCount, $this->previousCohortCustomersCount > 0 ? 'Base del mes anterior para comparar velocidad de activacion.' : 'Aun no hay cohorte previa suficiente para comparar.'),
            $this->metric('Repeat rate cohorte', $this->cohortRepeatRate.'%', $this->currentCohortCustomersCount > 0 ? 'Mide cuántos clientes del mes actual ya dieron una segunda compra o cita.' : 'Necesitas clientes nuevos para medir repetición temprana.'),
            $this->metric('LTV cohorte actual', $this->formatCurrency($this->cohortLtv), $this->cohortLtv > 0 ? 'Valor medio acumulado que ya deja la cohorte del mes.' : 'Aun no hay gasto suficiente en la cohorte actual.'),
            $this->metric('Canal loyalty dominante', $this->topLoyaltyChannel, $this->hasLoyaltyProgram ? 'Canal que mas sostiene interaccion en clientes con fidelidad activa.' : 'Activa fidelidad para detectar el canal que mejor sostiene continuidad.'),
            $this->metric('Canjes 30 días', (string) $this->loyaltyRedeems30d, $this->loyaltyRedeems30d > 0 ? 'Clientes que ya canjearon beneficios en el periodo reciente.' : 'Todavía no hay canjes recientes conectados a tus campañas.'),
            $this->metric('Campañas programadas', (string) $this->scheduledCampaignsCount, $this->scheduledCampaignsCount > 0 ? 'Ya tienes empujes preparados para sostener la cadencia comercial.' : 'Todavía no hay envíos agendados saliendo solos.'),
            $this->metric('Cadencias activas', (string) $this->recurringCampaignsCount, $this->recurringCampaignsCount > 0 ? 'Campañas que siguen operando con repetición configurada.' : 'Todavía no has encendido campañas recurrentes por segmento.'),
        ];
    }

    private function buildOperationalComparisons(): void
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            $this->ownerComparisons = [];
            $this->locationComparisons = [];

            return;
        }
        /** @var GetTenantOperationalComparisonsHandler $handler */
        $handler = app(GetTenantOperationalComparisonsHandler::class);
        $summary = $handler->handle(new GetTenantOperationalComparisonsQuery(
            tenantId: (string) $tenant->getKey(),
        ));

        $this->ownerComparisons = $summary['ownerComparisons'];
        $this->locationComparisons = $summary['locationComparisons'];
    }

    private function buildConversationPerformanceTables(): void
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            $this->templatePerformance = [];
            $this->sequencePerformance = [];
            $this->sequenceAttribution = [];
            $this->conversationPathAttribution = [];
            $this->ownerSequenceAttribution = [];
            $this->locationSequenceAttribution = [];
            $this->channelSequenceAttribution = [];
            $this->sequenceOutcomeAttribution = [];
            $this->ownerConversationPerformance = [];
            $this->locationConversationPerformance = [];

            return;
        }
        /** @var GetTenantJourneyAnalyticsHandler $handler */
        $handler = app(GetTenantJourneyAnalyticsHandler::class);
        $summary = $handler->handle(new GetTenantJourneyAnalyticsQuery(
            tenantId: (string) $tenant->getKey(),
        ));

        $this->templatePerformance = $summary['templatePerformance'];
        $this->sequencePerformance = $summary['sequencePerformance'];
        $this->sequenceAttribution = $summary['sequenceAttribution'];
        $this->conversationPathAttribution = $summary['conversationPathAttribution'];
        $this->ownerSequenceAttribution = $summary['ownerSequenceAttribution'];
        $this->locationSequenceAttribution = $summary['locationSequenceAttribution'];
        $this->channelSequenceAttribution = $summary['channelSequenceAttribution'];
        $this->sequenceOutcomeAttribution = $summary['sequenceOutcomeAttribution'];
        $this->upgradeAssistedRevenue30d = $summary['upgradeAssistedRevenue30d'];
        $this->ownerConversationPerformance = $summary['ownerConversationPerformance'];
        $this->locationConversationPerformance = $summary['locationConversationPerformance'];
    }

    private function buildGrowthActions(): void
    {
        $actions = [
            $this->action('Abrir clientes', 'CRM operativo parcial: fichas, notas y reactivacion por cliente.', $this->customersUrl),
            $this->action('Ver contactos', 'Audita correos, seguimientos y entregas del negocio.', $this->contactsUrl),
            $this->action('Abrir campañas', 'Programa cadencias por segmento y seguimiento sin prometer automatizaciones completas.', $this->campaignsUrl),
            $this->action('Bandeja de respuestas', 'Mueve replies, opt-in y seguimiento humano desde una sola cola.', $this->inboxUrl),
            $this->action('Abrir prospectos', 'Gestiona leads por estado, sin mezclarlo con pipeline de oportunidades.', $this->leadsUrl),
            $this->action(
                'Ir a oportunidades',
                'Abre deals solo cuando el tenant tiene pipeline CRM habilitado.',
                $this->dealsUrl,
                $this->canAccessCrmDeals,
                $this->canUpgradeToPro
                    ? 'Requiere upgrade y feature CRM pipelines para este tenant.'
                    : 'Requiere permiso de lectura sobre oportunidades CRM para tu rol.'
            ),
            $this->action('Ir a marketing', 'Ajusta CTA, QR, WhatsApp y campañas.', $this->marketingUrl),
            $this->action('Ver centro operativo', 'Convierte automatizaciones en cola con SLA.', $this->operationsUrl),
        ];

        if ($this->businessType === BusinessType::SERVICE->value) {
            $actions[] = $this->action('Ver agenda', 'Usa citas y asistencia como motor de continuidad.', $this->appointmentsUrl);
        } else {
            $actions[] = $this->action('Ver pedidos', 'Une conversión con ticket y recompra.', $this->ordersUrl);
        }

        if ($this->hasLoyaltyFeature) {
            $actions[] = $this->action('Audiencia y fidelidad', 'Mueve seguidores, loyalty y reactivación con más criterio.', $this->hasLoyaltyProgram ? $this->loyaltyUrl : $this->followersUrl);
        }

        $this->growthActions = array_slice($actions, 0, 7);
    }

    private function buildPriorityQueue(): void
    {
        $queue = [];

        if ($this->newLeadsCount > $this->contactedLeadsCount) {
            $queue[] = $this->priority('Responde prospectos nuevos', 'Tienes mas leads esperando primer toque que leads ya trabajados.', $this->leadsUrl, 'danger');
        }

        if ($this->visits30d >= 20 && ($this->newLeadsCount + $this->contactedLeadsCount + $this->closedLeadsCount) === 0) {
            $queue[] = $this->priority('Cierra mejor tu captura', 'Ya hay tráfico, pero casi no estás convirtiendo visitas en conversaciones.', $this->marketingUrl, 'warning');
        }

        if ($this->repeatCustomerCount > 0 && ! $this->hasLoyaltyProgram && $this->hasLoyaltyFeature) {
            $queue[] = $this->priority('Activa fidelidad', 'Ya existe recompra: conviene convertirla en programa y no dejarla al azar.', $this->loyaltyUrl, 'warning');
        }

        if ($this->campaignsSent30d === 0 && $this->hasMarketingSuite && ($this->followersCount > 0 || $this->closedLeadsCount > 0 || $this->campaignReadyCustomersCount > 0)) {
            $queue[] = $this->priority('Lanza una campaña', $this->campaignReadyCustomersCount > 0 ? 'Tu CRM ya tiene base opt-in lista para una campaña por segmento, owner o sede.' : 'Tu base ya aguanta un empuje comercial y todavía no lo estás usando.', $this->marketingUrl, 'info');
        }

        if ($this->hasCampaignFeature && $this->campaignReadyCustomersCount > 0 && $this->recurringCampaignsCount === 0) {
            $queue[] = $this->priority('Enciende una cadencia recurrente', 'Ya tienes base con permiso claro, pero ninguna campaña recurrente sosteniendo recompra o reactivación.', $this->campaignsUrl, 'warning');
        }

        if ($this->hasLoyaltyProgram && $this->redeemableCustomersCount > 0 && $this->rewardCampaignsCount === 0) {
            $queue[] = $this->priority('Activa rewards en campañas', 'Tienes clientes listos para canjear, pero ninguna campaña está empujando ese momento de conversión.', $this->campaignsUrl, 'info');
        }

        if ($this->overduePlaybooksCount > 0) {
            $queue[] = $this->priority('Atiende playbooks vencidos', 'Tus automatizaciones ya generaron cola atrasada y conviene moverla hoy.', $this->operationsUrl, 'danger');
        }

        if ($this->touchpoints30d > 0 && $this->deliveryRate30d < 70) {
            $queue[] = $this->priority('Limpia la entregabilidad', 'Tus correos recientes no estan llegando con la fuerza esperada. Ajusta opt-in, copy o reputacion.', $this->contactsUrl, 'warning');
        }

        if ($this->dueTouchpointRetriesCount > 0) {
            $queue[] = $this->priority('Rescata retries CRM', 'Ya tienes contactos listos para reintento. Conviene revisar plantilla, destinatario y causa raiz antes de dejar que la cola se acumule.', $this->contactsUrl, 'warning');
        }

        if ($this->breachedRepliesCount > 0) {
            $queue[] = $this->priority('Rescata conversaciones vencidas', 'Ya tienes replies fuera de SLA. Conviene responder antes de empujar más campañas o follow-up.', $this->inboxUrl, 'danger');
        } elseif ($this->openRepliesCount > 0) {
            $queue[] = $this->priority('Responde tu bandeja', 'Hay conversaciones abiertas en CRM que conviene cerrar antes de lanzar mas automatizaciones.', $this->inboxUrl, 'danger');
        }

        if ($this->stalledJourneysCount > 0) {
            $queue[] = $this->priority('Interviene secuencias degradadas', 'Ya tienes journeys trabados por outcome, no-show o falta de respuesta. Conviene rescatar esos casos con ownership humano.', $this->inboxUrl, 'danger');
        }

        if ($this->redeemableCustomersCount > 0 && $this->hasLoyaltyProgram) {
            $queue[] = $this->priority('Activa recompensas listas', 'Ya existen clientes con puntos suficientes para canjear. Esto puede mover recompra y buena experiencia.', $this->customersUrl, 'info');
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

    private function buildCadencePlaybooks(): void
    {
        $playbooks = collect((array) ($this->growthProfile['cadence_playbooks'] ?? []))
            ->map(function (array $playbook): array {
                $readiness = $this->resolveAutomationReadiness((string) ($playbook['state'] ?? ''));

                return [
                    'title' => (string) ($playbook['title'] ?? ''),
                    'cadence' => (string) ($playbook['cadence'] ?? 'Manual'),
                    'trigger' => (string) ($playbook['trigger'] ?? ''),
                    'channel' => (string) ($playbook['channel'] ?? 'CRM'),
                    'copy' => (string) ($playbook['copy'] ?? ''),
                    'href' => $this->resolveGrowthHref((string) ($playbook['route_key'] ?? 'campaigns')),
                    'ready' => $readiness['ready'],
                    'statusLabel' => $readiness['label'],
                    'statusColor' => $readiness['color'],
                ];
            })
            ->filter(static fn (array $playbook): bool => $playbook['title'] !== '')
            ->values()
            ->all();

        $this->cadencePlaybooks = $playbooks;
    }

    /**
     * @return array{ready: bool, label: string, color: string}
     */
    private function resolveAutomationReadiness(string $state): array
    {
        return match ($state) {
            'whatsapp_channel' => $this->readiness($this->hasWhatsApp, 'Canal listo', 'Configura WhatsApp'),
            'whatsapp_and_leads' => $this->readiness($this->hasWhatsApp && ($this->newLeadsCount + $this->contactedLeadsCount + $this->closedLeadsCount) > 0, 'Lista para operar', 'Necesita canal y prospectos activos'),
            'appointments_ready' => $this->readiness($this->appointments30d > 0 || $this->businessType === BusinessType::SERVICE->value, 'Agenda lista', 'Activa agenda o citas'),
            'mixed_activity' => $this->readiness(($this->orders30d + $this->appointments30d) > 0, 'Actividad lista', 'Necesita pedidos o citas'),
            'audience_ready' => $this->readiness(($this->followersCount + $this->newLeadsCount + $this->contactedLeadsCount + $this->closedLeadsCount) > 0, 'Audiencia lista', 'Necesita audiencia'),
            'orders_or_audience' => $this->readiness($this->orders30d > 0 || $this->followersCount > 0, 'Base lista', 'Necesita pedidos o audiencia'),
            'promo_growth' => $this->readiness($this->hasMarketingSuite, 'Lista para promo', 'Requiere suite comercial'),
            'loyalty_growth' => $this->readiness($this->hasLoyaltyFeature && ($this->hasLoyaltyProgram || $this->repeatCustomerCount > 0), $this->hasLoyaltyProgram ? 'Loyalty activo' : 'Base lista para loyalty', 'Requiere loyalty o recompra'),
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
            'campaigns' => $this->campaignsUrl,
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
            return '';
        }
    }

    /**
     * @param  class-string<\Filament\Pages\Page>  $page
     */
    private function safePageUrl(string $page, array $parameters = []): string
    {
        try {
            if (method_exists($page, 'canAccess') && ! $page::canAccess()) {
                return '';
            }

            return $page::getUrl($parameters);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    private function safeResourceUrl(string $resource, array $parameters = []): string
    {
        try {
            if (method_exists($resource, 'canAccess') && ! $resource::canAccess()) {
                return '';
            }

            if (method_exists($resource, 'canViewAny') && ! $resource::canViewAny()) {
                return '';
            }

            return $resource::getUrl('index', $parameters);
        } catch (\Throwable) {
            return '';
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
     * @return array{title: string, copy: string, href: string, enabled: bool, reason: string}
     */
    private function action(string $title, string $copy, string $href, ?bool $enabled = null, string $reason = ''): array
    {
        $isEnabled = $enabled ?? ($href !== '');

        return [
            'title' => $title,
            'copy' => $copy,
            'href' => $href,
            'enabled' => $isEnabled,
            'reason' => $isEnabled ? '' : ($reason !== '' ? $reason : 'No disponible para este tenant o para tu rol actual.'),
        ];
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
}
