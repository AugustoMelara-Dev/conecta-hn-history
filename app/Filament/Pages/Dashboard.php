<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\TenantGovernance\Services\SuperAdminDashboardViewStateReader;
use App\Domain\TenantGovernance\Services\TenantCrmPortfolioManager;
use App\Domain\TenantGovernance\Services\TenantJourneyOpsMonitor;
use App\Models\Tenant;
use Filament\Panel;

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
            $state = app(SuperAdminDashboardViewStateReader::class)->read([
                'payments' => $this->paymentsUrl,
                'tenants' => $this->tenantsUrl,
                'operations' => $this->operationsUrl,
                'billing' => $this->billingUrl,
            ], $this->safeRoute(...));

            $this->hydrateFromViewState($state);
        } catch (\Throwable) {
            $this->hydrateFromViewState([]);
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function hydrateFromViewState(array $state): void
    {
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
        $this->openAttentionCount = (int) ($state['openAttentionCount'] ?? 0);
        $this->attentionQueues = (array) ($state['attentionQueues'] ?? []);
        $this->operationalLanes = (array) ($state['operationalLanes'] ?? []);
        $this->globalWorkboard = (array) ($state['globalWorkboard'] ?? []);
        $this->assigneeLoad = (array) ($state['assigneeLoad'] ?? []);
        $this->ownerPortfolio = (array) ($state['ownerPortfolio'] ?? []);
        $this->teamPortfolio = (array) ($state['teamPortfolio'] ?? []);
        $this->slaBacklog = (array) ($state['slaBacklog'] ?? []);
        $this->commercialFunnel = (array) ($state['commercialFunnel'] ?? []);
        $this->ownerGoals = (array) ($state['ownerGoals'] ?? []);
        $this->teamGoals = (array) ($state['teamGoals'] ?? []);
        $this->verticalMonthlyGoals = (array) ($state['verticalMonthlyGoals'] ?? []);
        $this->activationCohorts = (array) ($state['activationCohorts'] ?? []);
        $this->planCohorts = (array) ($state['planCohorts'] ?? []);
        $this->cityCohorts = (array) ($state['cityCohorts'] ?? []);
        $this->channelCohorts = (array) ($state['channelCohorts'] ?? []);
        $this->ownerCohorts = (array) ($state['ownerCohorts'] ?? []);
        $this->segmentExpansion = (array) ($state['segmentExpansion'] ?? []);
        $this->verticalChurn = (array) ($state['verticalChurn'] ?? []);
        $this->verticalJourneyUplift = (array) ($state['verticalJourneyUplift'] ?? []);
        $this->revenuePlaybooks = (array) ($state['revenuePlaybooks'] ?? []);
        $this->networkJourneyPaths = (array) ($state['networkJourneyPaths'] ?? []);
        $this->churnWatchlist = (array) ($state['churnWatchlist'] ?? []);
        $this->commercialFollowups = (array) ($state['commercialFollowups'] ?? []);
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
