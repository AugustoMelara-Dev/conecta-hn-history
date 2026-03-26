<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\TenantGovernance\Services\SuperAdminDashboardStateReader;
use App\Domain\TenantGovernance\Services\TenantCrmPortfolioManager;
use App\Domain\TenantGovernance\Services\TenantJourneyOpsMonitor;
use App\Models\OperationalPlaybookTask;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Filament\Panel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Dashboard extends \Filament\Pages\Page
{
    protected static bool $isDiscovered = false;

    protected static string $routePath = '/';

    protected static ?int $navigationSort = -2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Escritorio';

    protected static ?string $title = 'Escritorio';

    protected string $view = 'filament.pages.dashboard';

    public int $tenantsCount = 0;

    public int $proTenantsCount = 0;

    public int $usersCount = 0;

    public int $proConversionsThisMonthCount = 0;

    public int $pendingPaymentsCount = 0;

    public int $pendingTenantsCount = 0;

    public int $suspendedTenantsCount = 0;

    public int $atRiskTenantsCount = 0;

    public int $catalogGapCount = 0;

    public int $expiringProCount = 0;

    public int $openAttentionCount = 0;

    public int $activePlaybookCount = 0;

    public int $overduePlaybookCount = 0;

    public int $crmOverdueAccountsCount = 0;

    public int $crmWatchlistCount = 0;

    public int $crmOwnedAccountsCount = 0;

    public int $crmFollowupsTodayCount = 0;

    public int $unassignedPlaybookCount = 0;

    public int $commercialFollowupCount = 0;

    public int $renewalPlaybookCount = 0;

    public int $activationPlaybookCount = 0;

    public int $growthPlaybookCount = 0;

    public string $tenantsUrl = '#';

    public string $usersUrl = '#';

    public string $paymentsUrl = '#';

    public string $settingsUrl = '#';

    public string $billingUrl = '#';

    public string $operationsUrl = '#';

    /** @var list<array{label: string, count: int, copy: string, href: string, tone: string}> */
    public array $attentionQueues = [];

    /** @var list<array{label: string, count: int, overdue: int, unassigned: int, copy: string, href: string, tone: string}> */
    public array $operationalLanes = [];

    /** @var list<array{task: string, business: string, owner: string, internal_owner: string, assignee: string, follow_up: string, next_touch: string, queue: string, sla: string, reason: string, tone: string, href: string}> */
    public array $globalWorkboard = [];

    /** @var list<array{name: string, context: string, task_count: int, overdue_count: int, next_touch_count: int, tone: string, href: string}> */
    public array $assigneeLoad = [];

    /** @var list<array{name: string, context: string, account_count: int, overdue_count: int, watch_count: int, renewal_count: int, tone: string, href: string}> */
    public array $ownerPortfolio = [];

    /** @var list<array{team: string, context: string, account_count: int, overdue_count: int, watch_count: int, next_touch_count: int, tone: string, href: string}> */
    public array $teamPortfolio = [];

    /** @var list<array{business: string, owner: string, team: string, status: string, backlog: string, next_touch: string, watch: string, tone: string, href: string}> */
    public array $slaBacklog = [];

    /** @var list<array{stage: string, count: int, share: string, copy: string, tone: string, href: string}> */
    public array $commercialFunnel = [];

    /** @var list<array{owner: string, accounts: int, goal: int, covered: int, gap: int, coverage: string, mix: string, tone: string, href: string}> */
    public array $ownerGoals = [];

    /** @var list<array{team: string, portfolio: int, goal: int, covered: int, gap: int, focus: string, tone: string, href: string}> */
    public array $teamGoals = [];

    /** @var list<array{vertical: string, target: int, converted: int, pipeline: int, pro: int, attainment: string, focus: string, tone: string, href: string}> */
    public array $verticalMonthlyGoals = [];

    /** @var list<array{label: string, count: int, activation_rate: string, onboarding: int, active: int, pro: int, href: string}> */
    public array $activationCohorts = [];

    /** @var list<array{plan: string, count: int, converted: int, expansion: int, watch: int, pro: int, tone: string, href: string}> */
    public array $planCohorts = [];

    /** @var list<array{city: string, count: int, activation_rate: string, expansion: int, watch: int, pro: int, converted: int, tone: string, href: string}> */
    public array $cityCohorts = [];

    /** @var list<array{channel: string, count: int, activation_rate: string, expansion: int, watch: int, pro: int, copy: string, tone: string, href: string}> */
    public array $channelCohorts = [];

    /** @var list<array{owner: string, count: int, converted: int, expansion: int, watch: int, pro: int, tone: string, href: string}> */
    public array $ownerCohorts = [];

    /** @var list<array{segment: string, count: int, expansion: int, activation: int, pro: int, converted: int, watch: int, coverage: string, tone: string, href: string}> */
    public array $segmentExpansion = [];

    /** @var list<array{vertical: string, count: int, watch: int, critical: int, suspended: int, renewal: int, tone: string, href: string}> */
    public array $verticalChurn = [];

    /** @var list<array{playbook: string, business: string, queue: string, segment: string, owner: string, follow_up: string, next_touch: string, sla: string, conversion_signal: string, tone: string, href: string}> */
    public array $revenuePlaybooks = [];

    /** @var list<array{vertical:string,assisted_revenue:string,total_revenue:string,uplift:string,conversations:int,assisted:int,top_path:string,tone:string,href:string}> */
    public array $verticalJourneyUplift = [];

    /** @var list<array{path:string,revenue:string,vertical:string,owners:string,channels:string,conversations:int,tone:string,href:string}> */
    public array $networkJourneyPaths = [];

    /** @var list<array{business: string, owner: string, internal_owner: string, reason: string, metric: string, flagged_at: string, tone: string, href: string}> */
    public array $churnWatchlist = [];

    /** @var list<array{business: string, playbook: string, queue: string, owner: string, assignee: string, follow_up: string, next_touch: string, sla: string, tone: string, href: string}> */
    public array $commercialFollowups = [];

    /** @var list<array{business: string, reference: string, amount: string, status: string, tone: string, reported_at: string}> */
    public array $recentPayments = [];

    /** @var list<OperationalPlaybookTask> */
    private array $activePlaybookTasks = [];

    /** @var list<Tenant> */
    private array $portfolioTenants = [];

    /** @var list<SubscriptionPayment> */
    private array $monthlyProConversions = [];

    public function mount(TenantCrmPortfolioManager $portfolioManager, TenantJourneyOpsMonitor $journeyOpsMonitor): void
    {
        $this->tenantsUrl = $this->safeRoute('filament.admin.resources.tenants.index');
        $this->usersUrl = $this->safeRoute('filament.admin.resources.users.index');
        $this->paymentsUrl = $this->safeRoute('filament.admin.resources.payments.index');
        $this->settingsUrl = $this->safeRoute('filament.admin.pages.manage-general-settings');
        $this->billingUrl = $this->safeRoute('filament.admin.pages.billing');
        $this->operationsUrl = $this->safeRoute('filament.admin.resources.operational-playbook-tasks.index');

        try {
            $portfolioManager->syncSignals();
        } catch (\Throwable) {
            // Evita que una sincronizacion CRM incompleta tumbe el dashboard del superadmin.
        }

        try {
            $journeyOpsMonitor->syncSignals();
        } catch (\Throwable) {
            // Evita que la lectura de journeys CRM tumbe el dashboard.
        }

        $this->loadDashboardState();
    }

    public static function getRoutePath(Panel $panel): string
    {
        return static::$routePath;
    }

    private function loadDashboardState(): void
    {
        try {
            $state = app(SuperAdminDashboardStateReader::class)->read([
                'payments' => $this->paymentsUrl,
                'tenants' => $this->tenantsUrl,
                'operations' => $this->operationsUrl,
                'billing' => $this->billingUrl,
            ], $this->safeRoute(...));

            $this->activePlaybookTasks = (array) ($state['activePlaybookTasks'] ?? []);
            $this->portfolioTenants = (array) ($state['portfolioTenants'] ?? []);
            $this->monthlyProConversions = (array) ($state['monthlyProConversions'] ?? []);
            $this->usersCount = (int) ($state['usersCount'] ?? 0);
            $this->pendingPaymentsCount = (int) ($state['pendingPaymentsCount'] ?? 0);
            $this->recentPayments = (array) ($state['recentPayments'] ?? []);
            $this->activePlaybookCount = (int) ($state['activePlaybookCount'] ?? 0);
            $this->overduePlaybookCount = (int) ($state['overduePlaybookCount'] ?? 0);
            $this->unassignedPlaybookCount = (int) ($state['unassignedPlaybookCount'] ?? 0);
            $this->commercialFollowupCount = (int) ($state['commercialFollowupCount'] ?? 0);
            $this->renewalPlaybookCount = (int) ($state['renewalPlaybookCount'] ?? 0);
            $this->activationPlaybookCount = (int) ($state['activationPlaybookCount'] ?? 0);
            $this->growthPlaybookCount = (int) ($state['growthPlaybookCount'] ?? 0);
            $this->tenantsCount = (int) ($state['tenantsCount'] ?? 0);
            $this->proTenantsCount = (int) ($state['proTenantsCount'] ?? 0);
            $this->proConversionsThisMonthCount = (int) ($state['proConversionsThisMonthCount'] ?? 0);
            $this->pendingTenantsCount = (int) ($state['pendingTenantsCount'] ?? 0);
            $this->suspendedTenantsCount = (int) ($state['suspendedTenantsCount'] ?? 0);
            $this->atRiskTenantsCount = (int) ($state['atRiskTenantsCount'] ?? 0);
            $this->catalogGapCount = (int) ($state['catalogGapCount'] ?? 0);
            $this->expiringProCount = (int) ($state['expiringProCount'] ?? 0);
            $this->crmWatchlistCount = (int) ($state['crmWatchlistCount'] ?? 0);
            $this->crmOverdueAccountsCount = (int) ($state['crmOverdueAccountsCount'] ?? 0);
            $this->crmOwnedAccountsCount = (int) ($state['crmOwnedAccountsCount'] ?? 0);
            $this->crmFollowupsTodayCount = (int) ($state['crmFollowupsTodayCount'] ?? 0);
            $snapshot = (array) ($state['snapshot'] ?? []);
        } catch (\Throwable) {
            $this->activePlaybookTasks = [];
            $this->portfolioTenants = [];
            $this->monthlyProConversions = [];
            $this->usersCount = 0;
            $this->pendingPaymentsCount = 0;
            $this->recentPayments = [];
            $this->activePlaybookCount = 0;
            $this->overduePlaybookCount = 0;
            $this->unassignedPlaybookCount = 0;
            $this->commercialFollowupCount = 0;
            $this->renewalPlaybookCount = 0;
            $this->activationPlaybookCount = 0;
            $this->growthPlaybookCount = 0;
            $this->tenantsCount = 0;
            $this->proTenantsCount = 0;
            $this->proConversionsThisMonthCount = 0;
            $this->pendingTenantsCount = 0;
            $this->suspendedTenantsCount = 0;
            $this->atRiskTenantsCount = 0;
            $this->catalogGapCount = 0;
            $this->expiringProCount = 0;
            $this->crmWatchlistCount = 0;
            $this->crmOverdueAccountsCount = 0;
            $this->crmOwnedAccountsCount = 0;
            $this->crmFollowupsTodayCount = 0;
            $snapshot = [];
        }

        $this->openAttentionCount = (int) ($snapshot['openAttentionCount'] ?? 0);
        $this->attentionQueues = (array) ($snapshot['attentionQueues'] ?? []);
        $this->operationalLanes = (array) ($snapshot['operationalLanes'] ?? []);
        $this->globalWorkboard = (array) ($snapshot['globalWorkboard'] ?? []);
        $this->assigneeLoad = (array) ($snapshot['assigneeLoad'] ?? []);
        $this->ownerPortfolio = (array) ($snapshot['ownerPortfolio'] ?? []);
        $this->teamPortfolio = (array) ($snapshot['teamPortfolio'] ?? []);
        $this->slaBacklog = (array) ($snapshot['slaBacklog'] ?? []);
        $this->commercialFunnel = (array) ($snapshot['commercialFunnel'] ?? []);
        $this->ownerGoals = (array) ($snapshot['ownerGoals'] ?? []);
        $this->teamGoals = (array) ($snapshot['teamGoals'] ?? []);
        $this->verticalMonthlyGoals = (array) ($snapshot['verticalMonthlyGoals'] ?? []);
        $this->activationCohorts = (array) ($snapshot['activationCohorts'] ?? []);
        $this->planCohorts = (array) ($snapshot['planCohorts'] ?? []);
        $this->cityCohorts = (array) ($snapshot['cityCohorts'] ?? []);
        $this->channelCohorts = (array) ($snapshot['channelCohorts'] ?? []);
        $this->ownerCohorts = (array) ($snapshot['ownerCohorts'] ?? []);
        $this->segmentExpansion = (array) ($snapshot['segmentExpansion'] ?? []);
        $this->verticalChurn = (array) ($snapshot['verticalChurn'] ?? []);
        $this->verticalJourneyUplift = (array) ($snapshot['verticalJourneyUplift'] ?? []);
        $this->revenuePlaybooks = (array) ($snapshot['revenuePlaybooks'] ?? []);
        $this->networkJourneyPaths = (array) ($snapshot['networkJourneyPaths'] ?? []);
        $this->churnWatchlist = (array) ($snapshot['churnWatchlist'] ?? []);
        $this->commercialFollowups = (array) ($snapshot['commercialFollowups'] ?? []);
    }

    /**
     * @return Collection<int, OperationalPlaybookTask>
     */
    private function activePlaybookCollection(): Collection
    {
        return collect($this->activePlaybookTasks);
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function portfolioCollection(): Collection
    {
        return collect($this->portfolioTenants);
    }

    /**
     * @return Collection<int, SubscriptionPayment>
     */
    private function monthlyProConversionCollection(): Collection
    {
        return collect($this->monthlyProConversions);
    }

    private function ownerNeedsRevenueMotion(Tenant $tenant): bool
    {
        return $this->needsActivationPush($tenant)
            || $this->isExpansionReady($tenant)
            || $this->isRenewalMotionCandidate($tenant)
            || (int) ($tenant->crm_watch_score ?? 0) > 0;
    }

    private function hasPublishedOffer(Tenant $tenant): bool
    {
        return (int) ($tenant->products_total ?? 0) > 0 || (int) ($tenant->services_total ?? 0) > 0;
    }

    private function hasDirectChannel(Tenant $tenant): bool
    {
        return filled($tenant->whatsapp_number) || filled($tenant->phone) || filled($tenant->website_url);
    }

    private function needsActivationPush(Tenant $tenant): bool
    {
        $crmStatus = (string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW);
        $daysSinceCreated = $tenant->created_at instanceof Carbon ? (int) $tenant->created_at->diffInDays(now()) : 999;

        return in_array($crmStatus, [Tenant::CRM_STATUS_NEW, Tenant::CRM_STATUS_ONBOARDING], true)
            || (! $this->hasPublishedOffer($tenant) && $daysSinceCreated <= 30);
    }

    private function isExpansionReady(Tenant $tenant): bool
    {
        $crmStatus = (string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW);
        $daysSinceCreated = $tenant->created_at instanceof Carbon ? (int) $tenant->created_at->diffInDays(now()) : 0;

        return in_array($crmStatus, [Tenant::CRM_STATUS_ACTIVE, Tenant::CRM_STATUS_UPSELL], true)
            && ! $tenant->is_pro
            && $this->hasPublishedOffer($tenant)
            && $this->hasDirectChannel($tenant)
            && $daysSinceCreated >= 7;
    }

    private function isRenewalMotionCandidate(Tenant $tenant): bool
    {
        $crmStatus = (string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW);

        return $crmStatus === Tenant::CRM_STATUS_RENEWAL
            || ($tenant->is_pro && $tenant->subscription_ends_at instanceof Carbon && $tenant->subscription_ends_at->lte(now()->addDays(7)));
    }

    private function isActivatedTenant(Tenant $tenant): bool
    {
        return in_array((string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW), [
            Tenant::CRM_STATUS_ACTIVE,
            Tenant::CRM_STATUS_UPSELL,
            Tenant::CRM_STATUS_RENEWAL,
        ], true);
    }

    private function revenueGoalForTeam(string $team): int
    {
        return match ($team) {
            Tenant::CRM_TEAM_ACTIVATION => $this->portfolioCollection()->filter(fn (Tenant $tenant): bool => $this->needsActivationPush($tenant))->count(),
            Tenant::CRM_TEAM_GROWTH => $this->portfolioCollection()->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count(),
            Tenant::CRM_TEAM_BILLING => $this->portfolioCollection()->filter(fn (Tenant $tenant): bool => $this->isRenewalMotionCandidate($tenant))->count() + $this->pendingPaymentsCount,
            Tenant::CRM_TEAM_RETENTION => $this->portfolioCollection()->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count(),
            default => 0,
        };
    }

    private function safeRoute(string $name, array $parameters = []): string
    {
        try {
            return route($name, $parameters, false);
        } catch (\Throwable) {
            return '#';
        }
    }
}
