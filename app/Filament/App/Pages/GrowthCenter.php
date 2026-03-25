<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Services\BusinessGrowthBlueprint;
use App\Domain\TenantGovernance\Services\CustomerProfileManager;
use App\Domain\TenantGovernance\Services\BusinessSegmentResolver;
use App\Filament\App\Resources\AppointmentResource;
use App\Filament\App\Resources\CampaignResource;
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
use App\Models\CustomerConversation;
use App\Models\CustomerLoyaltyLedger;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTask;
use App\Models\CustomerTouchpoint;
use App\Models\Follow;
use App\Models\Lead;
use App\Models\LoyaltyProgram;
use App\Models\Location;
use App\Models\OperationalPlaybookTask;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Models\TenantAnalytic;
use App\Models\User;
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

    public string $topOfferCopy = 'Todavia no hay suficiente actividad reciente para destacar una oferta.';

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

    public string $growthUrl = '#';

    public string $leadsUrl = '#';

    public string $customersUrl = '#';

    public string $contactsUrl = '#';

    public string $inboxUrl = '#';

    public string $marketingUrl = '#';

    public string $campaignsUrl = '#';

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

    /** @var list<array{label: string, value: string, copy: string}> */
    public array $engagementMetrics = [];

    /** @var list<array{label: string, value: string, copy: string}> */
    public array $cohortMetrics = [];

    /** @var list<array{name: string, revenue: string, customers: int, openTasks: int, overdueTasks: int, repeatRate: string, context: string}> */
    public array $ownerComparisons = [];

    /** @var list<array{name: string, revenue: string, customers: int, openTasks: int, overdueTasks: int, repeatRate: string, context: string}> */
    public array $locationComparisons = [];

    /** @var list<array{title: string, copy: string, href: string}> */
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
        $this->isPro = (bool) $tenant->is_pro;
        $this->businessType = $tenant->business_type?->value ?? BusinessType::RETAIL->value;
        $this->businessTypeLabel = (string) (config("business-types.options.{$this->businessType}.label") ?? 'Negocio');
        $this->hasWhatsApp = filled($tenant->whatsapp_number);

        $this->growthUrl = $this->safeRoute('filament.app.pages.growth-center', ['tenant' => $tenantSlug]);
        $this->leadsUrl = $this->safeResourceUrl(LeadResource::class, ['tenant' => $tenantSlug]);
        $this->customersUrl = $this->safeResourceUrl(CustomerProfileResource::class, ['tenant' => $tenantSlug]);
        $this->contactsUrl = $this->safeResourceUrl(CustomerTouchpointResource::class, ['tenant' => $tenantSlug]);
        $this->inboxUrl = $this->safeRoute('filament.app.pages.customer-inbox', ['tenant' => $tenantSlug]);
        $this->marketingUrl = $this->safeRoute('filament.app.pages.manage-marketing', ['tenant' => $tenantSlug]);
        $this->campaignsUrl = $this->safeResourceUrl(CampaignResource::class, ['tenant' => $tenantSlug]);
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
        $this->buildEngagementMetrics();
        $this->buildCohortMetrics();
        $this->buildGrowthActions();
        $this->buildPriorityQueue();
        $this->buildAutomationJourneys();
        $this->buildCadencePlaybooks();
        $this->buildOperationalComparisons();
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
            $this->loadTopProductOffer($tenantId, $thirtyDaysAgo);
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

            if (in_array($this->businessType, [BusinessType::SERVICE->value, BusinessType::HYBRID->value], true)) {
                $this->loadTopServiceOffer($tenantId, $thirtyDaysAgo);
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
            $emailOptInCount = (clone $profilesBase)->where('email_opt_in', true)->count();
            $this->averageInitialLtv = round((float) ((clone $profilesBase)->avg('initial_ltv') ?: 0), 2);
            $this->reactivationPoolCount = max(
                $this->reactivationPoolCount,
                (clone $profilesBase)->where('reactivation_score', '>=', 65)->count(),
            );
            $this->repeatCustomerCount = max(
                $this->repeatCustomerCount,
                (clone $profilesBase)->where('repeat_purchase_count', '>', 0)->count(),
            );
            $this->distinctCustomersCount = max($this->distinctCustomersCount, $this->crmProfilesCount);
            $this->reactivatedCustomers30d = (clone $profilesBase)
                ->whereNotNull('last_reactivated_at')
                ->where('last_reactivated_at', '>=', $thirtyDaysAgo)
                ->count();
            $topReactivationSegment = (clone $profilesBase)
                ->whereNotNull('last_reactivated_at')
                ->where('last_reactivated_at', '>=', $thirtyDaysAgo)
                ->selectRaw('coalesce(manual_segment, customer_segment, "new") as segment_key, count(*) as total')
                ->groupBy('segment_key')
                ->orderByDesc('total')
                ->first();

            if ($topReactivationSegment !== null) {
                $this->topReactivationSegment = ucfirst(str_replace('_', ' ', (string) $topReactivationSegment->segment_key));
            }
            $this->loyaltyReadyCustomersCount = (clone $profilesBase)
                ->whereIn('loyalty_tier', ['silver', 'gold', 'vip'])
                ->count();
            $this->redeemableCustomersCount = (clone $profilesBase)
                ->where('redeemable_rewards_count', '>', 0)
                ->count();
            $this->campaignReadyCustomersCount = (clone $profilesBase)
                ->where('marketing_opt_in', true)
                ->where(function ($query): void {
                    $query->where(function ($email): void {
                        $email->whereNotNull('email')->where('email_opt_in', true);
                    })->orWhere(function ($whatsapp): void {
                        $whatsapp->whereNotNull('phone')->where('whatsapp_opt_in', true);
                    });
                })
                ->count();
            $this->assignedCustomersCount = (clone $profilesBase)
                ->whereNotNull('assigned_user_id')
                ->count();
            $this->locatedCustomersCount = (clone $profilesBase)
                ->whereNotNull('location_id')
                ->count();
            $this->vipCustomersCount = (clone $profilesBase)
                ->where(function ($query): void {
                    $query->where('manual_segment', 'vip')
                        ->orWhere(function ($nested): void {
                            $nested->whereNull('manual_segment')->where('customer_segment', 'vip');
                        });
                })
                ->count();
            $this->dormantCustomersCount = (clone $profilesBase)
                ->where('lifecycle_stage', 'dormant')
                ->count();
            $this->optInCoverageRate = $this->crmProfilesCount > 0
                ? round(($emailOptInCount / $this->crmProfilesCount) * 100, 1)
                : 0.0;

            $topAcquisition = (clone $profilesBase)
                ->selectRaw('coalesce(acquisition_channel, "manual") as channel_key, count(*) as total')
                ->groupBy('channel_key')
                ->orderByDesc('total')
                ->first();

            if ($topAcquisition !== null) {
                $this->topAcquisitionChannel = $this->formatSourceLabel((string) $topAcquisition->channel_key);
            }

            $topSegment = (clone $profilesBase)
                ->selectRaw('coalesce(manual_segment, customer_segment, "new") as segment_key, count(*) as total')
                ->groupBy('segment_key')
                ->orderByDesc('total')
                ->first();

            if ($topSegment !== null) {
                $this->topCustomerSegment = ucfirst(str_replace('_', ' ', (string) $topSegment->segment_key));
            }

            $repeatProfileIds = (clone $profilesBase)
                ->where('repeat_purchase_count', '>', 0)
                ->pluck('id');

            if ($repeatProfileIds->isNotEmpty()) {
                $this->repeatRevenue30d = round((float) Order::query()
                    ->withoutGlobalScopes()
                    ->without(['items'])
                    ->where('tenant_id', $tenantId)
                    ->whereIn('customer_profile_id', $repeatProfileIds->all())
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->whereNotIn('status', [Order::STATUS_CANCELLED])
                    ->sum('total'), 2);
            }

            $currentCohortStart = now()->startOfMonth();
            $previousCohortStart = now()->copy()->subMonthNoOverflow()->startOfMonth();
            $previousCohortEnd = $currentCohortStart->copy()->subSecond();

            $currentCohortBase = (clone $profilesBase)
                ->whereNotNull('first_seen_at')
                ->where('first_seen_at', '>=', $currentCohortStart);

            $previousCohortBase = (clone $profilesBase)
                ->whereNotNull('first_seen_at')
                ->whereBetween('first_seen_at', [$previousCohortStart, $previousCohortEnd]);

            $this->currentCohortCustomersCount = (clone $currentCohortBase)->count();
            $this->previousCohortCustomersCount = (clone $previousCohortBase)->count();
            $this->cohortRepeatRate = $this->currentCohortCustomersCount > 0
                ? round(((clone $currentCohortBase)->where('repeat_purchase_count', '>', 0)->count() / $this->currentCohortCustomersCount) * 100, 1)
                : 0.0;
            $this->cohortLtv = round((float) ((clone $currentCohortBase)->avg('total_spent') ?: 0), 2);

            $topLtvSegment = (clone $profilesBase)
                ->selectRaw('coalesce(manual_segment, customer_segment, "new") as segment_key, avg(total_spent) as avg_ltv')
                ->where('total_spent', '>', 0)
                ->groupBy('segment_key')
                ->orderByDesc('avg_ltv')
                ->first();

            if ($topLtvSegment !== null) {
                $this->topLtvSegment = ucfirst(str_replace('_', ' ', (string) $topLtvSegment->segment_key));
            }

            $topLtvChannel = (clone $profilesBase)
                ->selectRaw('coalesce(first_conversion_channel, acquisition_channel, "manual") as channel_key, avg(total_spent) as avg_ltv')
                ->where('total_spent', '>', 0)
                ->groupBy('channel_key')
                ->orderByDesc('avg_ltv')
                ->first();

            if ($topLtvChannel !== null) {
                $this->topLtvChannel = $this->formatSourceLabel((string) $topLtvChannel->channel_key);
            }
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
            $this->scheduledCampaignsCount = Campaign::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('scheduled_at')
                ->whereIn('status', ['draft', 'scheduled', 'queued', 'sending'])
                ->count();
            $this->recurringCampaignsCount = Campaign::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_recurring', true)
                ->count();
            $this->rewardCampaignsCount = Campaign::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('loyalty_reward_id')
                ->count();
        } catch (\Throwable) {
            // Campaigns are optional for free tenants.
        }

        try {
            $touchpointsBase = CustomerTouchpoint::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $thirtyDaysAgo);

            $this->touchpoints30d = (clone $touchpointsBase)
                ->where('status', CustomerTouchpoint::STATUS_SENT)
                ->count();

            $this->scheduledTouchpointRetriesCount = CustomerTouchpoint::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('next_retry_at')
                ->count();
            $this->dueTouchpointRetriesCount = CustomerTouchpoint::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('next_retry_at')
                ->where('next_retry_at', '<=', now())
                ->count();

            $topTemplate = (clone $touchpointsBase)
                ->selectRaw("coalesce(template_key, automation_key, 'manual') as template_key, count(*) as total, sum(case when delivery_status = 'replied' then 1 else 0 end) as replied_total")
                ->groupBy('template_key')
                ->orderByDesc('replied_total')
                ->orderByDesc('total')
                ->first();

            if ($topTemplate !== null) {
                $this->topTemplateLabel = $this->formatSourceLabel((string) $topTemplate->template_key);
            }

            $emailTouchpoints = CustomerTouchpoint::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('channel', 'email')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->where('status', CustomerTouchpoint::STATUS_SENT);

            $totalEmailTouchpoints = (clone $emailTouchpoints)->count();
            $this->touchpointsDelivered30d = (clone $emailTouchpoints)->whereNotNull('delivered_at')->count();
            $this->touchpointsOpened30d = (clone $emailTouchpoints)->whereNotNull('opened_at')->count();
            $this->touchpointsBounced30d = (clone $emailTouchpoints)
                ->where(function ($query): void {
                    $query->whereNotNull('bounced_at')->orWhereNotNull('complained_at');
                })
                ->count();
            $whatsAppTouchpoints = CustomerTouchpoint::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('channel', 'whatsapp')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->where('status', CustomerTouchpoint::STATUS_SENT);

            $this->whatsAppTouchpoints30d = (clone $whatsAppTouchpoints)->count();
            $this->whatsAppReplies30d = (clone $whatsAppTouchpoints)->whereNotNull('replied_at')->count();
            $this->deliveryRate30d = $totalEmailTouchpoints > 0
                ? round(($this->touchpointsDelivered30d / $totalEmailTouchpoints) * 100, 1)
                : 0.0;
            $this->openRate30d = $totalEmailTouchpoints > 0
                ? round(($this->touchpointsOpened30d / $totalEmailTouchpoints) * 100, 1)
                : 0.0;
        } catch (\Throwable) {
            // Touchpoints are optional until CRM automation starts.
        }

        try {
            $conversationBase = CustomerConversation::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId);

            $customerConversations30d = (clone $conversationBase)
                ->whereNotNull('last_customer_message_at')
                ->where('last_customer_message_at', '>=', $thirtyDaysAgo);

            $respondedConversations30d = (clone $customerConversations30d)
                ->whereNotNull('first_response_at');

            $conversationsCount30d = (clone $customerConversations30d)->count();
            $this->responseRate30d = $conversationsCount30d > 0
                ? round(((clone $respondedConversations30d)->count() / $conversationsCount30d) * 100, 1)
                : 0.0;
            $this->openRepliesCount = (clone $conversationBase)
                ->whereIn('status', ['open', 'in_progress'])
                ->count();
            $this->breachedRepliesCount = (clone $conversationBase)
                ->whereIn('status', ['open', 'in_progress'])
                ->whereNotNull('response_due_at')
                ->where('response_due_at', '<', now())
                ->count();
            $this->resolvedReplies30d = (clone $conversationBase)
                ->where('status', 'closed')
                ->whereNotNull('closed_at')
                ->where('closed_at', '>=', $thirtyDaysAgo)
                ->count();

            $topClosingChannel = (clone $conversationBase)
                ->where('status', 'closed')
                ->whereNotNull('closed_at')
                ->where('closed_at', '>=', $thirtyDaysAgo)
                ->selectRaw('coalesce(source_channel, "crm") as channel_key, count(*) as total')
                ->groupBy('channel_key')
                ->orderByDesc('total')
                ->first();

            if ($topClosingChannel !== null) {
                $this->topClosingChannel = $this->formatSourceLabel((string) $topClosingChannel->channel_key);
            }

            $notesBase = CustomerNote::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId);

            $this->notes7dCount = (clone $notesBase)
                ->where('created_at', '>=', now()->subDays(7))
                ->count();
            $this->openCustomerTasksCount = CustomerTask::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();
            $this->overdueCustomerTasksCount = CustomerTask::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereNotNull('sla_due_at')
                ->where('sla_due_at', '<', now())
                ->count();

            $this->averageFirstResponseMinutes = round((float) $respondedConversations30d
                ->get(['first_response_at', 'last_customer_message_at'])
                ->map(function (CustomerConversation $conversation): int {
                    return max(0, (int) $conversation->last_customer_message_at?->diffInMinutes($conversation->first_response_at));
                })
                ->avg(), 1);
        } catch (\Throwable) {
            $this->notes7dCount = 0;
            $this->openRepliesCount = 0;
            $this->breachedRepliesCount = 0;
            $this->resolvedReplies30d = 0;
            $this->openCustomerTasksCount = 0;
            $this->overdueCustomerTasksCount = 0;
            $this->averageFirstResponseMinutes = 0.0;
            $this->responseRate30d = 0.0;
            $this->topClosingChannel = 'Sin datos';
        }

        try {
            $this->hasLoyaltyProgram = LoyaltyProgram::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->exists();

            $this->loyaltyRedeems30d = CustomerLoyaltyLedger::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('type', CustomerLoyaltyLedger::TYPE_REDEEM)
                ->where('awarded_at', '>=', $thirtyDaysAgo)
                ->count();

            $topLoyaltyChannel = CustomerProfile::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('lifetime_points_earned', '>', 0)
                ->selectRaw('coalesce(last_engaged_channel, preferred_channel, acquisition_channel, "manual") as channel_key, count(*) as total')
                ->groupBy('channel_key')
                ->orderByDesc('total')
                ->first();

            if ($topLoyaltyChannel !== null) {
                $this->topLoyaltyChannel = $this->formatSourceLabel((string) $topLoyaltyChannel->channel_key);
            }
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

        $this->recurrenceRate = $this->distinctCustomersCount > 0
            ? round(($this->repeatCustomerCount / $this->distinctCustomersCount) * 100, 1)
            : 0.0;
    }

    private function buildRevenueMetrics(): void
    {
        $leadCaptureRate = $this->visits30d > 0
            ? round((($this->newLeadsCount + $this->contactedLeadsCount + $this->closedLeadsCount) / $this->visits30d) * 100, 1)
            : 0.0;

        $this->revenueMetrics = [
            $this->metric('Ventas 30 dias', $this->formatCurrency($this->grossSales30d), "{$this->orders30d} operaciones registradas en el periodo."),
            $this->metric('Ticket promedio', $this->formatCurrency($this->averageTicket30d), 'Una lectura rapida para ver si estas vendiendo mejor o solo mas.'),
            $this->metric('Ingreso recurrente', $this->formatCurrency($this->repeatRevenue30d), $this->repeatRevenue30d > 0 ? 'Caja reciente movida por clientes que ya regresaron al menos una vez.' : 'Todavia no hay ingreso recurrente fuerte en el periodo.'),
            $this->metric('Captura de leads', $leadCaptureRate.'%', $this->visits30d > 0 ? "Con {$this->visits30d} visitas unicas recientes." : 'Todavia no hay trafico sincronizado suficiente.'),
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
            $this->metric('Retries pendientes', (string) $this->dueTouchpointRetriesCount, $this->dueTouchpointRetriesCount > 0 ? 'Hay contactos listos para reintento automatico o manual.' : 'La cola de retries esta sana en este momento.'),
            $this->metric('Bandeja abierta', (string) $this->openRepliesCount, $this->openRepliesCount > 0 ? 'Conversaciones esperando respuesta o cierre humano.' : 'No hay replies pendientes en la cola activa.'),
            $this->metric('Canjeables hoy', (string) $this->redeemableCustomersCount, $this->redeemableCustomersCount > 0 ? 'Clientes que ya pueden canjear una recompensa visible.' : 'Todavia no hay clientes listos para un canje inmediato.'),
            $this->metric('Listos para campaÃ±a', (string) $this->campaignReadyCustomersCount, $this->campaignReadyCustomersCount > 0 ? 'Base con opt-in y canal util para una campaÃ±a segmentada.' : 'Todavia falta base con permiso claro para campaÃ±as CRM.'),
            $this->metric('Con owner', (string) $this->assignedCustomersCount, $this->assignedCustomersCount > 0 ? 'Clientes ya asignados a alguien del equipo para continuidad.' : 'Todavia no has repartido cartera entre el equipo.'),
            $this->metric('Con sede', (string) $this->locatedCustomersCount, $this->locatedCustomersCount > 0 ? 'Clientes ya conectados a una sucursal o punto de atencion.' : 'Todavia no hay base suficiente etiquetada por sede.'),
            $this->metric('Reactivar', (string) $this->reactivationPoolCount, $this->reactivationPoolCount > 0 ? 'Clientes con senales para volver a tocar.' : 'La base reciente no muestra fuga grande todavia.'),
            $this->metric('Reactivados 30 dias', (string) $this->reactivatedCustomers30d, $this->reactivatedCustomers30d > 0 ? 'Clientes que ya respondieron a una nueva activacion reciente.' : 'Aun no tienes reactivaciones recientes registradas.'),
            $this->metric('Segmento que reactiva', $this->topReactivationSegment, $this->reactivatedCustomers30d > 0 ? 'El segmento que mas esta volviendo despues de tus journeys y follow-up.' : 'Todavia no hay suficiente reactivacion para detectar un segmento ganador.'),
            $this->metric('Tier con fidelidad', (string) $this->loyaltyReadyCustomersCount, $this->loyaltyReadyCustomersCount > 0 ? 'Clientes listos para beneficios, bundles o continuidad.' : 'Todavia no hay base suficiente para fidelidad segmentada.'),
        ];
    }

    private function buildEngagementMetrics(): void
    {
        $this->engagementMetrics = [
            $this->metric('Oferta top', $this->topOfferLabel, $this->topOfferCopy),
            $this->metric('LTV inicial', $this->formatCurrency($this->averageInitialLtv), $this->averageInitialLtv > 0 ? 'Promedio del valor capturado en los primeros 30 dias desde la primera conversion.' : 'Aun no hay conversion suficiente para estimar LTV inicial.'),
            $this->metric('Recurrencia', $this->recurrenceRate.'%', $this->repeatCustomerCount > 0 ? "{$this->repeatCustomerCount} clientes ya compraron o reservaron mas de una vez." : 'Todavia no se ve repeticion fuerte en la base.'),
            $this->metric('Canal dominante', $this->topAcquisitionChannel, 'La entrada que mas clientes reales esta trayendo al CRM hoy.'),
            $this->metric('Segmento dominante', $this->topCustomerSegment, 'El grupo que hoy concentra mas clientes dentro del CRM.'),
            $this->metric('Segmento con mejor LTV', $this->topLtvSegment, 'El cluster que hoy deja mas valor acumulado por cliente.'),
            $this->metric('Canal con mejor LTV', $this->topLtvChannel, 'La entrada que esta trayendo clientes con mejor vida util.'),
            $this->metric('Primer respuesta', $this->averageFirstResponseMinutes > 0 ? $this->averageFirstResponseMinutes.' min' : 'Sin datos', $this->averageFirstResponseMinutes > 0 ? 'Tiempo medio entre reply del cliente y primer movimiento del equipo.' : 'Todavia no hay conversaciones respondidas para medir velocidad.'),
            $this->metric('Tasa de respuesta', $this->responseRate30d.'%', $this->responseRate30d > 0 ? 'Porcentaje de conversaciones recientes donde el equipo alcanzo primera respuesta.' : 'Todavia no hay suficiente volumen conversacional para medir respuesta.'),
            $this->metric('SLA vencido', (string) $this->breachedRepliesCount, $this->breachedRepliesCount > 0 ? 'Casos de conversacion ya fuera de tiempo comercial.' : 'No hay replies vencidos en este momento.'),
            $this->metric('Entrega de email', $this->deliveryRate30d.'%', $this->touchpoints30d > 0 ? "{$this->touchpointsDelivered30d} contactos llegaron a bandeja de entrada." : 'Aun no hay seguimiento suficiente para medir entrega.'),
            $this->metric('Apertura de email', $this->openRate30d.'%', $this->touchpointsOpened30d > 0 ? "{$this->touchpointsOpened30d} contactos ya abrieron tus mensajes." : 'Todavia no hay aperturas registradas por proveedor.'),
            $this->metric('Respuestas por WhatsApp', (string) $this->whatsAppReplies30d, $this->whatsAppTouchpoints30d > 0 ? "{$this->whatsAppReplies30d} conversaciones devolvieron respuesta reciente." : 'Todavia no hay conversaciones de WhatsApp suficientes para medir respuesta.'),
            $this->metric('Plantilla que mas convierte', $this->topTemplateLabel, $this->touchpoints30d > 0 ? 'Es la plantilla con mejor senal reciente de respuesta o cierre dentro del CRM.' : 'Todavia no hay suficiente volumen para detectar una plantilla ganadora.'),
            $this->metric('Canal que mas cierra', $this->topClosingChannel, $this->resolvedReplies30d > 0 ? 'Es el canal que mas conversaciones esta cerrando en el periodo reciente.' : 'Aun no hay suficientes cierres recientes para detectar un canal dominante.'),
            $this->metric('Retries programados', (string) $this->scheduledTouchpointRetriesCount, $this->scheduledTouchpointRetriesCount > 0 ? 'La cola ya tiene reintentos futuros listos para ejecutar.' : 'No hay reintentos en espera ahora mismo.'),
        ];
    }

    private function buildCohortMetrics(): void
    {
        $this->cohortMetrics = [
            $this->metric('Cohorte actual', (string) $this->currentCohortCustomersCount, $this->currentCohortCustomersCount > 0 ? 'Clientes captados en el mes actual que ya entraron al CRM.' : 'Todavia no hay clientes nuevos en la cohorte del mes.'),
            $this->metric('Cohorte previa', (string) $this->previousCohortCustomersCount, $this->previousCohortCustomersCount > 0 ? 'Base del mes anterior para comparar velocidad de activacion.' : 'Aun no hay cohorte previa suficiente para comparar.'),
            $this->metric('Repeat rate cohorte', $this->cohortRepeatRate.'%', $this->currentCohortCustomersCount > 0 ? 'Mide cuantos clientes del mes actual ya dieron una segunda compra o cita.' : 'Necesitas clientes nuevos para medir repeticion temprana.'),
            $this->metric('LTV cohorte actual', $this->formatCurrency($this->cohortLtv), $this->cohortLtv > 0 ? 'Valor medio acumulado que ya deja la cohorte del mes.' : 'Aun no hay gasto suficiente en la cohorte actual.'),
            $this->metric('Canal loyalty dominante', $this->topLoyaltyChannel, $this->hasLoyaltyProgram ? 'Canal que mas sostiene interaccion en clientes con fidelidad activa.' : 'Activa fidelidad para detectar el canal que mejor sostiene continuidad.'),
            $this->metric('Canjes 30 dias', (string) $this->loyaltyRedeems30d, $this->loyaltyRedeems30d > 0 ? 'Clientes que ya canjearon beneficios en el periodo reciente.' : 'Todavia no hay canjes recientes conectados a tus campaÃ±as.'),
            $this->metric('Campanas programadas', (string) $this->scheduledCampaignsCount, $this->scheduledCampaignsCount > 0 ? 'Ya tienes empujes preparados para sostener la cadencia comercial.' : 'Todavia no hay envios agendados saliendo solos.'),
            $this->metric('Cadencias activas', (string) $this->recurringCampaignsCount, $this->recurringCampaignsCount > 0 ? 'Campanas que siguen operando con repeticion configurada.' : 'Todavia no has encendido campanas recurrentes por segmento.'),
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

        $tenantId = (string) $tenant->getKey();
        $profiles = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get([
                'id',
                'assigned_user_id',
                'location_id',
                'customer_segment',
                'total_spent',
                'repeat_purchase_count',
            ]);
        $tasks = CustomerTask::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->get([
                'assigned_user_id',
                'location_id',
                'status',
                'sla_due_at',
            ]);

        $userLabels = User::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $profiles->pluck('assigned_user_id')->merge($tasks->pluck('assigned_user_id'))->filter()->unique()->all())
            ->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(string) $id => (string) $name])
            ->all();
        $locationLabels = Location::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $profiles->pluck('location_id')->merge($tasks->pluck('location_id'))->filter()->unique()->all())
            ->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(string) $id => (string) $name])
            ->all();

        $this->ownerComparisons = $this->buildPortfolioComparisonRows($profiles, $tasks, 'assigned_user_id', $userLabels, 'Sin owner');
        $this->locationComparisons = $this->buildPortfolioComparisonRows($profiles, $tasks, 'location_id', $locationLabels, 'Sin sede');
    }

    private function buildGrowthActions(): void
    {
        $actions = [
            $this->action('Abrir clientes', 'Trabaja fichas, notas, segmentos y reactivacion desde un CRM unificado.', $this->customersUrl),
            $this->action('Ver contactos', 'Audita correos, seguimientos y entregas del negocio.', $this->contactsUrl),
            $this->action('Abrir campanas', 'Programa cadencias por segmento, owner, sede y reward desde el CRM.', $this->campaignsUrl),
            $this->action('Bandeja de respuestas', 'Mueve replies, opt-in y seguimiento humano desde una sola cola.', $this->inboxUrl),
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

        $this->growthActions = array_slice($actions, 0, 7);
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

        if ($this->campaignsSent30d === 0 && $this->isPro && ($this->followersCount > 0 || $this->closedLeadsCount > 0 || $this->campaignReadyCustomersCount > 0)) {
            $queue[] = $this->priority('Lanza una campana', $this->campaignReadyCustomersCount > 0 ? 'Tu CRM ya tiene base opt-in lista para una campaÃ±a por segmento, owner o sede.' : 'Tu base ya aguanta un empuje comercial y todavia no lo estas usando.', $this->marketingUrl, 'info');
        }

        if ($this->isPro && $this->campaignReadyCustomersCount > 0 && $this->recurringCampaignsCount === 0) {
            $queue[] = $this->priority('Enciende una cadencia recurrente', 'Ya tienes base con permiso claro, pero ninguna campana recurrente sosteniendo recompra o reactivacion.', $this->campaignsUrl, 'warning');
        }

        if ($this->hasLoyaltyProgram && $this->redeemableCustomersCount > 0 && $this->rewardCampaignsCount === 0) {
            $queue[] = $this->priority('Conecta rewards a campanas', 'Tienes clientes listos para canjear, pero ninguna campana esta empujando ese momento de conversion.', $this->campaignsUrl, 'info');
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
            $queue[] = $this->priority('Rescata conversaciones vencidas', 'Ya tienes replies fuera de SLA. Conviene responder antes de empujar mas campanas o follow-up.', $this->inboxUrl, 'danger');
        } elseif ($this->openRepliesCount > 0) {
            $queue[] = $this->priority('Responde tu bandeja', 'Hay conversaciones abiertas en CRM que conviene cerrar antes de lanzar mas automatizaciones.', $this->inboxUrl, 'danger');
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
     * @param  Collection<int, CustomerProfile>  $profiles
     * @param  Collection<int, CustomerTask>  $tasks
     * @param  array<string, string>  $labels
     * @return list<array{name: string, revenue: string, customers: int, openTasks: int, overdueTasks: int, repeatRate: string, context: string}>
     */
    private function buildPortfolioComparisonRows(
        Collection $profiles,
        Collection $tasks,
        string $dimensionKey,
        array $labels,
        string $emptyLabel,
    ): array {
        return $profiles->pluck($dimensionKey)
            ->merge($tasks->pluck($dimensionKey))
            ->map(fn (mixed $value): string => (string) ($value ?: 'none'))
            ->unique()
            ->values()
            ->map(function (string $key) use ($profiles, $tasks, $dimensionKey, $labels, $emptyLabel): array {
                $profileSlice = $profiles->filter(fn (CustomerProfile $profile): bool => (string) ($profile->{$dimensionKey} ?: 'none') === $key);
                $taskSlice = $tasks->filter(fn (CustomerTask $task): bool => (string) ($task->{$dimensionKey} ?: 'none') === $key);
                $customerCount = $profileSlice->count();
                $repeatCustomers = $profileSlice->filter(fn (CustomerProfile $profile): bool => (int) $profile->repeat_purchase_count > 0)->count();
                $overdueTasks = $taskSlice->filter(fn (CustomerTask $task): bool => $task->sla_due_at !== null && $task->sla_due_at->isPast())->count();
                $topSegment = (string) ($profileSlice->pluck('customer_segment')->filter()->countBy()->sortDesc()->keys()->first() ?? 'sin segmento');
                $revenue = (float) $profileSlice->sum('total_spent');

                return [
                    'name' => $labels[$key] ?? $emptyLabel,
                    'revenue' => $this->formatCurrency($revenue),
                    'customers' => $customerCount,
                    'openTasks' => $taskSlice->count(),
                    'overdueTasks' => $overdueTasks,
                    'repeatRate' => $customerCount > 0 ? number_format(($repeatCustomers / $customerCount) * 100, 1).'%' : '0.0%',
                    'context' => 'Segmento dominante: '.$this->formatSourceLabel($topSegment),
                ];
            })
            ->sortByDesc(fn (array $row): float => ($row['overdueTasks'] * 1000) + ($row['openTasks'] * 100) + (float) str_replace(['L. ', ','], '', $row['revenue']))
            ->take(4)
            ->values()
            ->all();
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

    private function loadTopProductOffer(string $tenantId, Carbon $since): void
    {
        $topProduct = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', $tenantId)
            ->where('orders.created_at', '>=', $since)
            ->whereNotIn('orders.status', [Order::STATUS_CANCELLED])
            ->selectRaw('coalesce(order_items.product_name, "Producto") as offer_name, sum(order_items.quantity) as units, sum(coalesce(order_items.line_total, order_items.total_price, 0)) as revenue')
            ->groupBy('offer_name')
            ->orderByDesc('revenue')
            ->orderByDesc('units')
            ->first();

        if ($topProduct !== null) {
            $this->topOfferLabel = (string) $topProduct->offer_name;
            $this->topOfferCopy = (int) ($topProduct->units ?? 0) > 0
                ? ((int) $topProduct->units).' unidades movieron '.$this->formatCurrency((float) ($topProduct->revenue ?? 0)).' en los ultimos 30 dias.'
                : 'Tu catalogo ya muestra una oferta dominante.';
        }
    }

    private function loadTopServiceOffer(string $tenantId, Carbon $since): void
    {
        $topService = Appointment::query()
            ->withoutGlobalScopes()
            ->leftJoin('services', 'services.id', '=', 'appointments.service_id')
            ->where('appointments.tenant_id', $tenantId)
            ->where('appointments.created_at', '>=', $since)
            ->whereNotIn('appointments.status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
            ->selectRaw('coalesce(services.name, "Servicio") as offer_name, count(*) as units')
            ->groupBy('offer_name')
            ->orderByDesc('units')
            ->first();

        if ($topService !== null && (int) ($topService->units ?? 0) > 0) {
            $this->topOfferLabel = (string) $topService->offer_name;
            $this->topOfferCopy = (int) $topService->units.' reservas recientes apuntan a esta oferta como la de mayor traccion.';
        }
    }
}











