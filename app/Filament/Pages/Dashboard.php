<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\TenantGovernance\Services\SuperAdminJourneyAnalyticsReader;
use App\Domain\TenantGovernance\Services\TenantCrmPortfolioManager;
use App\Domain\TenantGovernance\Services\TenantJourneyOpsMonitor;
use App\Models\CustomerConversation;
use App\Models\CustomerTouchpoint;
use App\Models\OperationalPlaybookTask;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

        $this->loadGlobalPlaybookState();
        $this->loadPortfolioTenants();
        $this->loadMonthlyProConversions();
        $this->loadMetrics();
        $this->loadAttentionQueues();
        $this->loadGlobalWorkboard();
        $this->loadOwnerPortfolio();
        $this->loadTeamPortfolio();
        $this->loadSlaBacklog();
        $this->loadCommercialFunnel();
        $this->loadOwnerGoals();
        $this->loadTeamGoals();
        $this->loadVerticalMonthlyGoals();
        $this->loadActivationCohorts();
        $this->loadPlanCohorts();
        $this->loadCityCohorts();
        $this->loadChannelCohorts();
        $this->loadOwnerCohorts();
        $this->loadSegmentExpansion();
        $this->loadVerticalChurn();
        $this->loadVerticalJourneyUplift();
        $this->loadRevenuePlaybooks();
        $this->loadNetworkJourneyPaths();
        $this->loadAssigneeLoadBoard();
        $this->loadChurnWatchlist();
        $this->loadCommercialFollowups();
        $this->loadRecentPayments();
    }

    public static function getRoutePath(Panel $panel): string
    {
        return static::$routePath;
    }

    private function loadMetrics(): void
    {
        try {
            $portfolio = $this->portfolioCollection();

            $this->tenantsCount = $portfolio->count();
            $this->proTenantsCount = $portfolio->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count();
            $this->usersCount = User::query()->count();
            $this->proConversionsThisMonthCount = $this->monthlyProConversionCollection()
                ->map(fn (SubscriptionPayment $payment): string => (string) $payment->tenant_id)
                ->filter()
                ->unique()
                ->count();
            $this->pendingPaymentsCount = SubscriptionPayment::query()
                ->withoutGlobalScopes()
                ->where('status', SubscriptionPayment::STATUS_PENDING)
                ->count();
            $this->pendingTenantsCount = $portfolio->filter(fn (Tenant $tenant): bool => (string) $tenant->getRawOriginal('status') === 'pending')->count();
            $this->suspendedTenantsCount = $portfolio->filter(fn (Tenant $tenant): bool => ! (bool) $tenant->getAttribute('is_active'))->count();
            $this->catalogGapCount = $portfolio
                ->filter(fn (Tenant $tenant): bool => (int) ($tenant->products_total ?? 0) === 0 && (int) ($tenant->services_total ?? 0) === 0)
                ->count();
            $this->expiringProCount = $portfolio
                ->filter(function (Tenant $tenant): bool {
                    return $tenant->is_pro
                        && $tenant->subscription_ends_at instanceof Carbon
                        && $tenant->subscription_ends_at->between(now(), now()->addDays(7));
                })
                ->count();
            $this->crmWatchlistCount = $portfolio
                ->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)
                ->count();
            $this->atRiskTenantsCount = $this->crmWatchlistCount;
            $this->crmOverdueAccountsCount = $portfolio
                ->filter(fn (Tenant $tenant): bool => $tenant->crm_next_follow_up_at instanceof Carbon && $tenant->crm_next_follow_up_at->isPast())
                ->count();
            $this->crmFollowupsTodayCount = $portfolio
                ->filter(function (Tenant $tenant): bool {
                    return $tenant->crm_next_follow_up_at instanceof Carbon
                        && $tenant->crm_next_follow_up_at->between(now()->startOfDay(), now()->endOfDay());
                })
                ->count();
            $this->crmOwnedAccountsCount = $portfolio
                ->filter(fn (Tenant $tenant): bool => filled($tenant->crm_owner_id))
                ->count();
        } catch (\Throwable) {
            $this->tenantsCount = 0;
            $this->proTenantsCount = 0;
            $this->usersCount = 0;
            $this->proConversionsThisMonthCount = 0;
            $this->pendingPaymentsCount = 0;
            $this->pendingTenantsCount = 0;
            $this->suspendedTenantsCount = 0;
            $this->atRiskTenantsCount = 0;
            $this->catalogGapCount = 0;
            $this->expiringProCount = 0;
            $this->crmWatchlistCount = 0;
            $this->crmOverdueAccountsCount = 0;
            $this->crmOwnedAccountsCount = 0;
            $this->crmFollowupsTodayCount = 0;
        }
    }

    private function loadPortfolioTenants(): void
    {
        try {
            $this->portfolioTenants = Tenant::query()
                ->with(['user:id,name,email', 'crmOwner:id,name,email', 'city:id,name,slug'])
                ->withCount([
                    'products as products_total' => fn (Builder $query): Builder => $query->withoutGlobalScopes(),
                    'services as services_total' => fn (Builder $query): Builder => $query->withoutGlobalScopes(),
                ])
                ->get()
                ->all();
        } catch (\Throwable) {
            $this->portfolioTenants = [];
        }
    }

    private function loadMonthlyProConversions(): void
    {
        try {
            $start = now()->startOfMonth();
            $end = now()->endOfMonth();

            $this->monthlyProConversions = SubscriptionPayment::query()
                ->withoutGlobalScopes()
                ->where('status', SubscriptionPayment::STATUS_APPROVED)
                ->whereNotNull('subscription_extended_at')
                ->whereBetween('subscription_extended_at', [$start, $end])
                ->with([
                    'tenant' => fn (Builder $query): Builder => $query
                        ->withoutGlobalScopes()
                        ->with(['crmOwner:id,name,email', 'city:id,name,slug', 'user:id,name,email']),
                ])
                ->orderByDesc('subscription_extended_at')
                ->get()
                ->all();
        } catch (\Throwable) {
            $this->monthlyProConversions = [];
        }
    }

    private function loadGlobalPlaybookState(): void
    {
        try {
            $tasks = OperationalPlaybookTask::query()
                ->withoutGlobalScopes()
                ->with(['tenant.user:id,name,email', 'tenant.crmOwner:id,name,email', 'assignee:id,name,email', 'adminOwner:id,name,email'])
                ->whereIn('status', [
                    OperationalPlaybookTask::STATUS_OPEN,
                    OperationalPlaybookTask::STATUS_IN_PROGRESS,
                ])
                ->where(function (Builder $query): void {
                    $query
                        ->whereNull('follow_up_status')
                        ->orWhere('follow_up_status', '!=', OperationalPlaybookTask::FOLLOW_UP_CLOSED);
                })
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
                        when due_at < now() then 0
                        else 1
                    end
                ")
                ->orderBy('due_at')
                ->get();

            $this->activePlaybookTasks = $tasks->all();
            $this->activePlaybookCount = $tasks->count();
            $this->overduePlaybookCount = $tasks->filter(fn (OperationalPlaybookTask $task): bool => $task->isOverdue())->count();
            $this->unassignedPlaybookCount = $tasks->filter(fn (OperationalPlaybookTask $task): bool => blank($task->admin_owner_id))->count();
            $this->commercialFollowupCount = $tasks->filter(
                fn (OperationalPlaybookTask $task): bool => in_array((string) $task->queue, ['leads', 'retention'], true)
            )->count();
            $this->renewalPlaybookCount = $tasks->filter(
                fn (OperationalPlaybookTask $task): bool => (string) $task->queue === 'billing'
            )->count();
            $this->activationPlaybookCount = $tasks->filter(
                fn (OperationalPlaybookTask $task): bool => (string) $task->queue === 'activation'
            )->count();
            $this->growthPlaybookCount = $tasks->filter(
                fn (OperationalPlaybookTask $task): bool => (string) $task->queue === 'growth'
            )->count();

            $this->operationalLanes = [
                $this->buildLaneCard($tasks, 'activation', 'Activacion', 'Onboarding comercial, oferta visible y primer valor entregado.', $this->operationsUrl),
                $this->buildLaneCard($tasks, 'growth', 'Expansion', 'Upsell, plan PRO y crecimiento sobre cuentas con base sana.', $this->operationsUrl),
                $this->buildLaneCard($tasks, 'leads', 'Captacion', 'Leads nuevos y primeras respuestas comerciales.', $this->operationsUrl),
                $this->buildLaneCard($tasks, 'appointments', 'Agenda', 'Reservas, confirmaciones y prevencion de no-show.', $this->operationsUrl),
                $this->buildLaneCard($tasks, 'retention', 'Retencion', 'Abandono, recompra y continuidad de clientes.', $this->operationsUrl),
                $this->buildLaneCard($tasks, 'billing', 'Renovacion', 'Cobro, continuidad de plan y churn prevention.', $this->operationsUrl),
            ];
        } catch (\Throwable) {
            $this->activePlaybookTasks = [];
            $this->activePlaybookCount = 0;
            $this->overduePlaybookCount = 0;
            $this->unassignedPlaybookCount = 0;
            $this->commercialFollowupCount = 0;
            $this->renewalPlaybookCount = 0;
            $this->activationPlaybookCount = 0;
            $this->growthPlaybookCount = 0;
            $this->operationalLanes = [];
        }
    }

    private function loadAttentionQueues(): void
    {
        $this->openAttentionCount = $this->pendingPaymentsCount
            + $this->pendingTenantsCount
            + $this->unassignedPlaybookCount
            + $this->overduePlaybookCount
            + $this->crmOverdueAccountsCount
            + $this->commercialFollowupCount
            + $this->activationPlaybookCount
            + $this->growthPlaybookCount
            + $this->expiringProCount
            + $this->crmWatchlistCount;

        $this->attentionQueues = [
            [
                'label' => 'Pagos por validar',
                'count' => $this->pendingPaymentsCount,
                'copy' => 'Comprobantes listos para revision antes de activar o rechazar PRO.',
                'href' => $this->paymentsUrl,
                'tone' => $this->pendingPaymentsCount > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Altas pendientes',
                'count' => $this->pendingTenantsCount,
                'copy' => 'Negocios nuevos que todavia necesitan aprobacion operativa.',
                'href' => $this->tenantsUrl,
                'tone' => $this->pendingTenantsCount > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'SLA vencido',
                'count' => $this->overduePlaybookCount,
                'copy' => 'Playbooks ya vencidos que deberian estar en manos de alguien hoy.',
                'href' => $this->operationsUrl,
                'tone' => $this->overduePlaybookCount > 0 ? 'danger' : 'success',
            ],
            [
                'label' => 'Sin owner interno',
                'count' => $this->unassignedPlaybookCount,
                'copy' => 'Casos sin responsable comercial interno y con riesgo de quedarse en el aire.',
                'href' => $this->operationsUrl,
                'tone' => $this->unassignedPlaybookCount > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Cartera vencida',
                'count' => $this->crmOverdueAccountsCount,
                'copy' => 'Negocios con siguiente toque atrasado y riesgo de enfriarse por falta de disciplina.',
                'href' => $this->tenantsUrl,
                'tone' => $this->crmOverdueAccountsCount > 0 ? 'danger' : 'success',
            ],
            [
                'label' => 'Seguimiento comercial',
                'count' => $this->commercialFollowupCount,
                'copy' => 'Leads, carritos y recompras que siguen vivos entre tenants.',
                'href' => $this->operationsUrl,
                'tone' => $this->commercialFollowupCount > 0 ? 'info' : 'success',
            ],
            [
                'label' => 'Activacion comercial',
                'count' => $this->activationPlaybookCount,
                'copy' => 'Negocios nuevos que todavia no terminan de activar oferta, canal o confianza.',
                'href' => $this->operationsUrl,
                'tone' => $this->activationPlaybookCount > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Expansion lista',
                'count' => $this->growthPlaybookCount,
                'copy' => 'Cuentas con base real para mover upgrade, plan PRO o growth comercial.',
                'href' => $this->operationsUrl,
                'tone' => $this->growthPlaybookCount > 0 ? 'info' : 'success',
            ],
            [
                'label' => 'PRO por vencer',
                'count' => $this->expiringProCount,
                'copy' => 'Renovaciones cercanas que merecen seguimiento antes del corte.',
                'href' => $this->billingUrl,
                'tone' => $this->expiringProCount > 0 ? 'danger' : 'success',
            ],
            [
                'label' => 'Churn en observacion',
                'count' => $this->crmWatchlistCount,
                'copy' => 'Cuentas persistidas en watchlist con motivo, tono y fecha de alerta.',
                'href' => $this->tenantsUrl,
                'tone' => $this->crmWatchlistCount > 0 ? 'warning' : 'success',
            ],
        ];
    }

    private function loadGlobalWorkboard(): void
    {
        $this->globalWorkboard = $this->activePlaybookCollection()
            ->take(6)
            ->map(fn (OperationalPlaybookTask $task): array => $this->mapWorkboardTask($task))
            ->values()
            ->all();
    }

    private function loadAssigneeLoadBoard(): void
    {
        $this->assigneeLoad = $this->activePlaybookCollection()
            ->groupBy(function (OperationalPlaybookTask $task): string {
                if (filled($task->admin_owner_id)) {
                    return 'user:'.(string) $task->admin_owner_id;
                }

                return 'unassigned';
            })
            ->map(function (Collection $tasks): array {
                /** @var OperationalPlaybookTask $first */
                $first = $tasks->first();
                $ownerName = $first->adminOwner?->name ?? 'Sin owner interno';
                $queueSummary = $tasks
                    ->map(fn (OperationalPlaybookTask $task): string => (string) $task->queue_label)
                    ->unique()
                    ->take(3)
                    ->implode(', ');
                $tenantCount = $tasks
                    ->map(fn (OperationalPlaybookTask $task): string => (string) $task->tenant_id)
                    ->unique()
                    ->count();
                $overdueCount = $tasks->filter(fn (OperationalPlaybookTask $task): bool => $task->isOverdue())->count();
                $taskCount = $tasks->count();
                $nextTouchCount = $tasks->filter(function (OperationalPlaybookTask $task): bool {
                    return $task->next_follow_up_at instanceof Carbon
                        && $task->next_follow_up_at->lte(now()->endOfDay());
                })->count();

                return [
                    'name' => $ownerName,
                    'context' => collect([
                        $tenantCount.' negocios',
                        $queueSummary !== '' ? $queueSummary : null,
                    ])->filter()->implode(' / '),
                    'task_count' => $taskCount,
                    'overdue_count' => $overdueCount,
                    'next_touch_count' => $nextTouchCount,
                    'tone' => $overdueCount > 0 ? 'danger' : ($ownerName === 'Sin owner interno' ? 'warning' : 'info'),
                    'href' => $this->operationsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['overdue_count'] * 100) + $item['task_count'])
            ->take(6)
            ->values()
            ->all();
    }

    private function loadOwnerPortfolio(): void
    {
        $tasksByTenant = $this->activePlaybookCollection()
            ->groupBy(fn (OperationalPlaybookTask $task): string => (string) $task->tenant_id);

        $this->ownerPortfolio = $this->portfolioCollection()
            ->groupBy(function (Tenant $tenant): string {
                if (filled($tenant->crm_owner_id)) {
                    return 'owner:'.(string) $tenant->crm_owner_id;
                }

                return 'owner:none';
            })
            ->map(function (Collection $tenants): array {
                /** @var Tenant $first */
                $first = $tenants->first();
                $overdueCount = $tenants->filter(fn (Tenant $tenant): bool => $tenant->crm_next_follow_up_at instanceof Carbon && $tenant->crm_next_follow_up_at->isPast())->count();
                $watchCount = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $renewalCount = $tenants->filter(function (Tenant $tenant): bool {
                    return (string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW) === Tenant::CRM_STATUS_RENEWAL
                        || ($tenant->is_pro && $tenant->subscription_ends_at instanceof Carbon && $tenant->subscription_ends_at->lte(now()->addDays(7)));
                })->count();
                $teamSummary = $tenants
                    ->map(fn (Tenant $tenant): string => (string) $tenant->crm_team_label)
                    ->unique()
                    ->take(3)
                    ->implode(', ');

                return [
                    'name' => $first->crmOwner?->name ?? 'Sin owner interno',
                    'context' => collect([
                        $teamSummary !== '' ? $teamSummary : null,
                        $tenants->count().' cuentas',
                    ])->filter()->implode(' / '),
                    'account_count' => $tenants->count(),
                    'overdue_count' => $overdueCount,
                    'watch_count' => $watchCount,
                    'renewal_count' => $renewalCount,
                    'tone' => $overdueCount > 0 ? 'danger' : ($watchCount > 0 ? 'warning' : (($first->crmOwner?->name ?? '') === '' ? 'gray' : 'info')),
                    'href' => $this->operationsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['watch_count'] * 100) + ($item['overdue_count'] * 50) + $item['account_count'])
            ->take(6)
            ->values()
            ->all();
    }

    private function loadTeamPortfolio(): void
    {
        $this->teamPortfolio = $this->portfolioCollection()
            ->groupBy(fn (Tenant $tenant): string => (string) ($tenant->getRawOriginal('crm_team') ?? Tenant::CRM_TEAM_ACTIVATION))
            ->map(function (Collection $tenants, string $team): array {
                $overdueCount = $tenants->filter(fn (Tenant $tenant): bool => $tenant->crm_next_follow_up_at instanceof Carbon && $tenant->crm_next_follow_up_at->isPast())->count();
                $watchCount = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $nextTouchCount = $tenants->filter(function (Tenant $tenant): bool {
                    return $tenant->crm_next_follow_up_at instanceof Carbon
                        && $tenant->crm_next_follow_up_at->between(now()->startOfDay(), now()->endOfDay());
                })->count();
                $statusSummary = $tenants
                    ->map(fn (Tenant $tenant): string => (string) $tenant->crm_status_label)
                    ->unique()
                    ->take(3)
                    ->implode(', ');

                return [
                    'team' => match ($team) {
                        Tenant::CRM_TEAM_GROWTH => 'Growth',
                        Tenant::CRM_TEAM_RETENTION => 'Retencion',
                        Tenant::CRM_TEAM_BILLING => 'Cobros',
                        Tenant::CRM_TEAM_SUPPORT => 'Soporte',
                        default => 'Activacion',
                    },
                    'context' => $statusSummary !== '' ? $statusSummary : 'Sin contexto adicional',
                    'account_count' => $tenants->count(),
                    'overdue_count' => $overdueCount,
                    'watch_count' => $watchCount,
                    'next_touch_count' => $nextTouchCount,
                    'tone' => $overdueCount > 0 ? 'danger' : ($watchCount > 0 ? 'warning' : 'info'),
                    'href' => $this->operationsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['watch_count'] * 100) + ($item['overdue_count'] * 50) + $item['account_count'])
            ->take(5)
            ->values()
            ->all();
    }

    private function loadSlaBacklog(): void
    {
        $this->slaBacklog = $this->portfolioCollection()
            ->filter(function (Tenant $tenant): bool {
                if ($tenant->crm_next_follow_up_at instanceof Carbon && $tenant->crm_next_follow_up_at->isPast()) {
                    return true;
                }

                return (int) ($tenant->crm_watch_score ?? 0) >= 80;
            })
            ->sortByDesc(function (Tenant $tenant): int {
                $overdueWeight = $tenant->crm_next_follow_up_at instanceof Carbon && $tenant->crm_next_follow_up_at->isPast()
                    ? abs($tenant->crm_next_follow_up_at->diffInMinutes(now(), false))
                    : 0;

                return ((int) ($tenant->crm_watch_score ?? 0) * 1000) + $overdueWeight;
            })
            ->take(6)
            ->map(function (Tenant $tenant): array {
                $nextTouch = $tenant->crm_next_follow_up_at;

                return [
                    'business' => (string) $tenant->name,
                    'owner' => $tenant->crmOwner?->name ?? 'Sin owner interno',
                    'team' => (string) $tenant->crm_team_label,
                    'status' => (string) $tenant->crm_status_label,
                    'backlog' => $nextTouch instanceof Carbon && $nextTouch->isPast()
                        ? 'Atrasado '.$nextTouch->diffForHumans()
                        : 'Alerta alta sin toque cerrado',
                    'next_touch' => $nextTouch instanceof Carbon ? $nextTouch->format('d/m H:i') : 'Sin fecha agendada',
                    'watch' => $tenant->crm_watch_label,
                    'tone' => (string) ($tenant->crm_watch_tone ?? 'warning'),
                    'href' => $this->safeRoute('filament.admin.resources.tenants.edit', ['record' => $tenant]),
                ];
            })
            ->values()
            ->all();
    }

    private function loadCommercialFunnel(): void
    {
        $total = max(1, $this->tenantsCount);
        $counts = $this->portfolioCollection()
            ->countBy(fn (Tenant $tenant): string => (string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW));

        $stages = [
            Tenant::CRM_STATUS_NEW => ['label' => 'Nuevo', 'copy' => 'Alta sin activacion comercial cerrada.', 'tone' => 'gray'],
            Tenant::CRM_STATUS_ONBOARDING => ['label' => 'Onboarding', 'copy' => 'Activando oferta, canales y equipo inicial.', 'tone' => 'info'],
            Tenant::CRM_STATUS_ACTIVE => ['label' => 'Operando', 'copy' => 'Cuenta en marcha y lista para profundizar valor.', 'tone' => 'success'],
            Tenant::CRM_STATUS_UPSELL => ['label' => 'Upsell', 'copy' => 'Cuenta lista para crecer ticket, modulos o plan.', 'tone' => 'warning'],
            Tenant::CRM_STATUS_RENEWAL => ['label' => 'Renovacion', 'copy' => 'Continuidad de plan en foco comercial.', 'tone' => 'danger'],
            Tenant::CRM_STATUS_AT_RISK => ['label' => 'En riesgo', 'copy' => 'Cuenta que exige recovery plan o soporte ejecutivo.', 'tone' => 'danger'],
            Tenant::CRM_STATUS_PAUSED => ['label' => 'Pausado', 'copy' => 'Cuenta frenada que necesita decision o rescate.', 'tone' => 'gray'],
        ];

        $this->commercialFunnel = collect($stages)
            ->map(function (array $stage, string $status) use ($counts, $total): array {
                $count = (int) ($counts[$status] ?? 0);

                return [
                    'stage' => $stage['label'],
                    'count' => $count,
                    'share' => number_format(($count / $total) * 100, 1).'%',
                    'copy' => $stage['copy'],
                    'tone' => $stage['tone'],
                    'href' => $this->tenantsUrl,
                ];
            })
            ->values()
            ->all();
    }

    private function loadOwnerGoals(): void
    {
        $this->ownerGoals = $this->portfolioCollection()
            ->filter(fn (Tenant $tenant): bool => $this->ownerNeedsRevenueMotion($tenant))
            ->groupBy(fn (Tenant $tenant): string => (string) ($tenant->crm_owner_id ?: 'unassigned'))
            ->map(function (Collection $tenants, string $ownerKey): array {
                $activation = $tenants->filter(fn (Tenant $tenant): bool => $this->needsActivationPush($tenant))->count();
                $growth = $tenants->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count();
                $watch = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $renewal = $tenants->filter(fn (Tenant $tenant): bool => $this->isRenewalMotionCandidate($tenant))->count();
                $goal = $activation + $growth + $watch + $renewal;
                $covered = $this->activePlaybookCollection()
                    ->filter(function (OperationalPlaybookTask $task) use ($ownerKey): bool {
                        $resolvedOwnerKey = (string) ($task->admin_owner_id ?? $task->tenant?->crm_owner_id ?? '');

                        if ($ownerKey === 'unassigned') {
                            return $resolvedOwnerKey === '' && in_array((string) $task->queue, ['activation', 'growth', 'billing', 'retention'], true);
                        }

                        return $resolvedOwnerKey === $ownerKey && in_array((string) $task->queue, ['activation', 'growth', 'billing', 'retention'], true);
                    })
                    ->count();
                $gap = max(0, $goal - $covered);
                /** @var Tenant|null $firstTenant */
                $firstTenant = $tenants->first();
                $ownerName = $ownerKey === 'unassigned'
                    ? 'Sin owner interno'
                    : (string) (($firstTenant?->crmOwner?->name ?: $firstTenant?->crmOwner?->email) ?? 'Owner interno');

                return [
                    'owner' => $ownerName,
                    'accounts' => $tenants->count(),
                    'goal' => $goal,
                    'covered' => $covered,
                    'gap' => $gap,
                    'coverage' => $goal > 0 ? number_format(($covered / $goal) * 100, 1).'%' : '0.0%',
                    'mix' => collect([
                        $activation > 0 ? $activation.' activacion' : null,
                        $growth > 0 ? $growth.' growth' : null,
                        $watch > 0 ? $watch.' watch' : null,
                        $renewal > 0 ? $renewal.' renovacion' : null,
                    ])->filter()->implode(' | '),
                    'tone' => $gap > 0 ? (($watch + $renewal) > 0 ? 'danger' : 'warning') : 'success',
                    'href' => $this->operationsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['gap'] * 100) + ($item['goal'] * 10) + $item['accounts'])
            ->take(6)
            ->values()
            ->all();
    }

    private function loadTeamGoals(): void
    {
        /** @var array<string, array{label?: string, focus?: string, queues?: array<int, string>}> $teams */
        $teams = config('revenue-ops.teams', []);

        $this->teamGoals = collect($teams)
            ->map(function (array $teamConfig, string $team): array {
                $goal = $this->revenueGoalForTeam($team);
                $queues = array_values(array_filter((array) ($teamConfig['queues'] ?? []), static fn (mixed $value): bool => is_string($value) && $value !== ''));
                $covered = $this->activePlaybookCollection()
                    ->filter(fn (OperationalPlaybookTask $task): bool => in_array((string) $task->queue, $queues, true))
                    ->count();
                $portfolio = $this->portfolioCollection()
                    ->filter(fn (Tenant $tenant): bool => (string) ($tenant->getRawOriginal('crm_team') ?? Tenant::CRM_TEAM_ACTIVATION) === $team)
                    ->count();
                $gap = max(0, $goal - $covered);

                return [
                    'team' => (string) ($teamConfig['label'] ?? str($team)->replace('_', ' ')->title()->value()),
                    'portfolio' => $portfolio,
                    'goal' => $goal,
                    'covered' => $covered,
                    'gap' => $gap,
                    'focus' => (string) ($teamConfig['focus'] ?? 'Sin foco configurado.'),
                    'tone' => $gap > 0 ? (($team === Tenant::CRM_TEAM_BILLING || $team === Tenant::CRM_TEAM_RETENTION) ? 'danger' : 'warning') : 'success',
                    'href' => $this->operationsUrl,
                ];
            })
            ->filter(fn (array $item): bool => $item['goal'] > 0 || $item['portfolio'] > 0 || $item['covered'] > 0)
            ->sortByDesc(fn (array $item): int => ($item['gap'] * 100) + ($item['goal'] * 10) + $item['portfolio'])
            ->values()
            ->all();
    }

    private function loadVerticalMonthlyGoals(): void
    {
        /** @var array<string, array{label?: string, target?: int, focus?: string}> $targets */
        $targets = config('revenue-ops.monthly_vertical_goals', []);

        $configuredSegments = collect(array_keys($targets))
            ->reject(fn (string $segmentKey): bool => $segmentKey === 'default');

        $activeSegments = $this->portfolioCollection()
            ->map(fn (Tenant $tenant): string => $this->segmentKeyForTenant($tenant))
            ->merge($this->monthlyProConversionCollection()->map(fn (SubscriptionPayment $payment): string => $this->segmentKeyForPayment($payment)))
            ->filter()
            ->unique();

        $this->verticalMonthlyGoals = $configuredSegments
            ->merge($activeSegments)
            ->unique()
            ->map(function (string $segmentKey) use ($targets): array {
                $verticalTenants = $this->portfolioCollection()
                    ->filter(fn (Tenant $tenant): bool => $this->segmentKeyForTenant($tenant) === $segmentKey);
                $count = $verticalTenants->count();
                $converted = $this->convertedTenantCount(
                    fn (SubscriptionPayment $payment): bool => $this->segmentKeyForPayment($payment) === $segmentKey
                );
                $pipeline = $verticalTenants->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count();
                $pro = $verticalTenants->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count();

                /** @var array{label?: string, target?: int, focus?: string} $defaultTarget */
                $defaultTarget = $targets['default'] ?? [];
                /** @var array{label?: string, target?: int, focus?: string} $targetConfig */
                $targetConfig = $targets[$segmentKey] ?? [];
                $target = max(1, (int) ($targetConfig['target'] ?? $defaultTarget['target'] ?? max(1, $pipeline)));
                $attainment = number_format(($converted / max(1, $target)) * 100, 1).'%';

                return [
                    'vertical' => (string) ($targetConfig['label'] ?? $this->segmentLabel($segmentKey)),
                    'target' => $target,
                    'converted' => $converted,
                    'pipeline' => $pipeline,
                    'pro' => $pro,
                    'attainment' => $attainment,
                    'focus' => (string) ($targetConfig['focus'] ?? $defaultTarget['focus'] ?? 'Sin foco comercial configurado para este vertical.'),
                    'tone' => $converted >= $target ? 'success' : (($converted + $pipeline) >= $target ? 'warning' : 'info'),
                    'href' => $this->billingUrl,
                ];
            })
            ->filter(fn (array $item): bool => $item['target'] > 0 || $item['converted'] > 0 || $item['pipeline'] > 0 || $item['pro'] > 0)
            ->sortByDesc(fn (array $item): int => ($item['converted'] * 100) + ($item['pipeline'] * 50) + ($item['target'] * 10) + $item['pro'])
            ->take(6)
            ->values()
            ->all();
    }

    private function loadActivationCohorts(): void
    {
        $cohorts = collect(range(5, 0))
            ->map(function (int $monthsAgo): Carbon {
                return now()->startOfMonth()->subMonths($monthsAgo);
            });

        $this->activationCohorts = $cohorts
            ->map(function (Carbon $month): array {
                $cohortTenants = $this->portfolioCollection()->filter(function (Tenant $tenant) use ($month): bool {
                    return $tenant->created_at instanceof Carbon
                        && $tenant->created_at->isSameMonth($month);
                });

                $count = $cohortTenants->count();
                $onboarding = $cohortTenants->filter(function (Tenant $tenant): bool {
                    return in_array((string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW), [
                        Tenant::CRM_STATUS_NEW,
                        Tenant::CRM_STATUS_ONBOARDING,
                    ], true);
                })->count();
                $active = $cohortTenants->filter(function (Tenant $tenant): bool {
                    return in_array((string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW), [
                        Tenant::CRM_STATUS_ACTIVE,
                        Tenant::CRM_STATUS_UPSELL,
                        Tenant::CRM_STATUS_RENEWAL,
                    ], true);
                })->count();
                $pro = $cohortTenants->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count();

                return [
                    'label' => $month->translatedFormat('M Y'),
                    'count' => $count,
                    'activation_rate' => $count > 0 ? number_format(($active / $count) * 100, 1).'%' : '0.0%',
                    'onboarding' => $onboarding,
                    'active' => $active,
                    'pro' => $pro,
                    'href' => $this->tenantsUrl,
                ];
            })
            ->values()
            ->all();
    }

    private function loadPlanCohorts(): void
    {
        $planKeys = $this->portfolioCollection()
            ->map(fn (Tenant $tenant): string => $this->planKeyForTenant($tenant))
            ->merge($this->monthlyProConversionCollection()->map(fn (SubscriptionPayment $payment): string => $this->planKeyForPayment($payment)))
            ->filter()
            ->unique();

        $this->planCohorts = $planKeys
            ->map(function (string $planKey): array {
                $tenants = $this->portfolioCollection()
                    ->filter(fn (Tenant $tenant): bool => $this->planKeyForTenant($tenant) === $planKey);
                $count = $tenants->count();
                $converted = $this->convertedTenantCount(
                    fn (SubscriptionPayment $payment): bool => $this->planKeyForPayment($payment) === $planKey
                );
                $expansion = $tenants->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count();
                $watch = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $pro = $tenants->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count();

                return [
                    'plan' => $this->planLabel($planKey),
                    'count' => $count,
                    'converted' => $converted,
                    'expansion' => $expansion,
                    'watch' => $watch,
                    'pro' => $pro,
                    'tone' => $converted > 0 ? 'success' : ($watch > 0 ? 'warning' : 'info'),
                    'href' => $this->billingUrl,
                ];
            })
            ->filter(fn (array $item): bool => $item['count'] > 0 || $item['converted'] > 0 || $item['pro'] > 0)
            ->sortByDesc(fn (array $item): int => ($item['converted'] * 100) + ($item['expansion'] * 50) + ($item['count'] * 10))
            ->values()
            ->all();
    }

    private function loadCityCohorts(): void
    {
        $this->cityCohorts = $this->portfolioCollection()
            ->groupBy(fn (Tenant $tenant): string => (string) ($tenant->city?->name ?: 'Sin ciudad'))
            ->map(function (Collection $tenants, string $city): array {
                $count = $tenants->count();
                $activated = $tenants->filter(fn (Tenant $tenant): bool => $this->isActivatedTenant($tenant))->count();
                $expansion = $tenants->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count();
                $watch = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $pro = $tenants->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count();
                $converted = $this->convertedTenantCount(
                    fn (SubscriptionPayment $payment): bool => $this->cityLabelForPayment($payment) === $city
                );

                return [
                    'city' => $city,
                    'count' => $count,
                    'activation_rate' => $count > 0 ? number_format(($activated / $count) * 100, 1).'%' : '0.0%',
                    'expansion' => $expansion,
                    'watch' => $watch,
                    'pro' => $pro,
                    'converted' => $converted,
                    'tone' => $converted > 0 ? 'success' : ($watch > 0 ? 'warning' : ($expansion > 0 ? 'info' : 'gray')),
                    'href' => $this->tenantsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['converted'] * 100) + ($item['count'] * 50) + ($item['watch'] * 25) + ($item['expansion'] * 10))
            ->take(6)
            ->values()
            ->all();
    }

    private function loadChannelCohorts(): void
    {
        $this->channelCohorts = $this->portfolioCollection()
            ->groupBy(fn (Tenant $tenant): string => $this->closingChannelKey($tenant))
            ->map(function (Collection $tenants, string $channelKey): array {
                $count = $tenants->count();
                $activated = $tenants->filter(fn (Tenant $tenant): bool => $this->isActivatedTenant($tenant))->count();
                $expansion = $tenants->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count();
                $watch = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $pro = $tenants->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count();

                return [
                    'channel' => $this->closingChannelLabel($channelKey),
                    'count' => $count,
                    'activation_rate' => $count > 0 ? number_format(($activated / $count) * 100, 1).'%' : '0.0%',
                    'expansion' => $expansion,
                    'watch' => $watch,
                    'pro' => $pro,
                    'copy' => $this->closingChannelCopy($channelKey),
                    'tone' => $channelKey === 'none' ? 'warning' : ($expansion > 0 ? 'success' : 'info'),
                    'href' => $this->tenantsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['count'] * 100) + ($item['expansion'] * 50) + ($item['watch'] * 25))
            ->values()
            ->all();
    }

    private function loadSegmentExpansion(): void
    {
        $this->segmentExpansion = $this->portfolioCollection()
            ->groupBy(fn (Tenant $tenant): string => $this->segmentKeyForTenant($tenant))
            ->map(function (Collection $tenants, string $segmentKey): array {
                $count = $tenants->count();
                $expansion = $tenants->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count();
                $activation = $tenants->filter(fn (Tenant $tenant): bool => $this->needsActivationPush($tenant))->count();
                $pro = $tenants->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count();
                $converted = $this->convertedTenantCount(
                    fn (SubscriptionPayment $payment): bool => $this->segmentKeyForPayment($payment) === $segmentKey
                );
                $watch = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $ownerCoverage = $tenants->filter(fn (Tenant $tenant): bool => filled($tenant->crm_owner_id))->count();

                return [
                    'segment' => $this->segmentLabel($segmentKey),
                    'count' => $count,
                    'expansion' => $expansion,
                    'activation' => $activation,
                    'pro' => $pro,
                    'converted' => $converted,
                    'watch' => $watch,
                    'coverage' => $count > 0 ? number_format(($ownerCoverage / $count) * 100, 1).'%' : '0.0%',
                    'tone' => $converted > 0 ? 'success' : ($expansion > 0 ? 'warning' : ($watch > 0 ? 'danger' : 'info')),
                    'href' => $this->tenantsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['converted'] * 120) + ($item['expansion'] * 100) + ($item['watch'] * 50) + $item['count'])
            ->take(6)
            ->values()
            ->all();
    }

    private function loadOwnerCohorts(): void
    {
        $this->ownerCohorts = $this->portfolioCollection()
            ->groupBy(fn (Tenant $tenant): string => (string) ($tenant->crm_owner_id ?: 'unassigned'))
            ->map(function (Collection $tenants, string $ownerKey): array {
                /** @var Tenant|null $firstTenant */
                $firstTenant = $tenants->first();
                $converted = $this->convertedTenantCount(
                    fn (SubscriptionPayment $payment): bool => $this->ownerKeyForPayment($payment) === $ownerKey
                );
                $expansion = $tenants->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count();
                $watch = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $pro = $tenants->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count();

                return [
                    'owner' => $ownerKey === 'unassigned'
                        ? 'Sin owner interno'
                        : (string) (($firstTenant?->crmOwner?->name ?: $firstTenant?->crmOwner?->email) ?? 'Owner interno'),
                    'count' => $tenants->count(),
                    'converted' => $converted,
                    'expansion' => $expansion,
                    'watch' => $watch,
                    'pro' => $pro,
                    'tone' => $converted > 0 ? 'success' : ($watch > 0 ? 'warning' : ($ownerKey === 'unassigned' ? 'gray' : 'info')),
                    'href' => $this->operationsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['converted'] * 100) + ($item['watch'] * 50) + ($item['expansion'] * 25) + $item['count'])
            ->take(6)
            ->values()
            ->all();
    }

    private function loadVerticalChurn(): void
    {
        $this->verticalChurn = $this->portfolioCollection()
            ->groupBy(fn (Tenant $tenant): string => $this->segmentKeyForTenant($tenant))
            ->map(function (Collection $tenants, string $segmentKey): array {
                $watch = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $critical = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) >= 90)->count();
                $suspended = $tenants->filter(fn (Tenant $tenant): bool => ! (bool) $tenant->getAttribute('is_active'))->count();
                $renewal = $tenants->filter(function (Tenant $tenant): bool {
                    return (string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW) === Tenant::CRM_STATUS_RENEWAL
                        || ($tenant->is_pro && $tenant->subscription_ends_at instanceof Carbon && $tenant->subscription_ends_at->lte(now()->addDays(7)));
                })->count();

                return [
                    'vertical' => $this->segmentLabel($segmentKey),
                    'count' => $tenants->count(),
                    'watch' => $watch,
                    'critical' => $critical,
                    'suspended' => $suspended,
                    'renewal' => $renewal,
                    'tone' => $critical > 0 ? 'danger' : ($watch > 0 || $renewal > 0 ? 'warning' : 'info'),
                    'href' => $this->tenantsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['critical'] * 100) + ($item['watch'] * 50) + $item['renewal'])
            ->take(6)
            ->values()
            ->all();
    }

    private function loadVerticalJourneyUplift(): void
    {
        /** @var SuperAdminJourneyAnalyticsReader $reader */
        $reader = app(SuperAdminJourneyAnalyticsReader::class);
        $this->verticalJourneyUplift = $reader->verticalJourneyUplift($this->operationsUrl);
    }

    private function loadRevenuePlaybooks(): void
    {
        $this->revenuePlaybooks = $this->activePlaybookCollection()
            ->filter(fn (OperationalPlaybookTask $task): bool => in_array((string) $task->queue, ['activation', 'growth', 'billing'], true))
            ->sortByDesc(function (OperationalPlaybookTask $task): int {
                $conversionWeight = (string) $task->queue === 'growth'
                    ? $this->convertedTenantCount(
                        fn (SubscriptionPayment $payment): bool => $this->segmentKeyForPayment($payment) === (string) ($task->segment_key ?? '')
                    ) * 100
                    : 0;

                return $conversionWeight + ($task->isOverdue() ? 1000 : 0);
            })
            ->take(8)
            ->map(function (OperationalPlaybookTask $task): array {
                $conversionSignal = $this->revenuePlaybookConversionSignal($task);

                return [
                    'playbook' => (string) $task->title,
                    'business' => (string) ($task->tenant?->name ?? 'Negocio sin nombre'),
                    'queue' => (string) $task->queue_label,
                    'segment' => $this->segmentLabel((string) ($task->segment_key ?? '')),
                    'owner' => $this->internalOwnerLabel($task),
                    'follow_up' => (string) $task->follow_up_status_label,
                    'next_touch' => $this->nextTouchLabel($task),
                    'sla' => $this->formatSla($task),
                    'conversion_signal' => $conversionSignal,
                    'tone' => (string) $this->revenuePlaybookTone($task, $conversionSignal),
                    'href' => $this->taskAdminHref($task),
                ];
            })
            ->values()
            ->all();
    }

    private function loadNetworkJourneyPaths(): void
    {
        /** @var SuperAdminJourneyAnalyticsReader $reader */
        $reader = app(SuperAdminJourneyAnalyticsReader::class);
        $this->networkJourneyPaths = $reader->networkJourneyPaths($this->operationsUrl);
    }

    private function loadChurnWatchlist(): void
    {
        try {
            $this->churnWatchlist = $this->portfolioCollection()
                ->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)
                ->sortByDesc(fn (Tenant $tenant): int => (int) ($tenant->crm_watch_score ?? 0))
                ->take(6)
                ->map(function (Tenant $tenant): array {
                    return [
                        'business' => (string) $tenant->name,
                        'owner' => $this->tenantOwnerLabel($tenant->user),
                        'internal_owner' => $tenant->crmOwner?->name ?? 'Sin owner interno',
                        'reason' => (string) ($tenant->crm_watch_reason ?? 'Cuenta en observacion.'),
                        'metric' => (string) ($tenant->crm_watch_metric ?? 'Watchlist activa'),
                        'flagged_at' => $tenant->crm_watch_flagged_at instanceof Carbon
                            ? 'Abierto '.$tenant->crm_watch_flagged_at->diffForHumans()
                            : 'Sin fecha',
                        'tone' => (string) ($tenant->crm_watch_tone ?? 'warning'),
                        'href' => $this->safeRoute('filament.admin.resources.tenants.edit', ['record' => $tenant]),
                    ];
                })
                ->values()
                ->all();
        } catch (\Throwable) {
            $this->churnWatchlist = [];
        }
    }

    private function loadCommercialFollowups(): void
    {
        $this->commercialFollowups = $this->activePlaybookCollection()
            ->filter(
                fn (OperationalPlaybookTask $task): bool => in_array((string) $task->queue, ['leads', 'retention'], true)
            )
            ->take(6)
            ->map(function (OperationalPlaybookTask $task): array {
                $tenant = $task->tenant;
                $assignee = $task->assignee?->name;

                if ($assignee === null || $assignee === '') {
                    $presetLabel = $this->presetLabel((string) ($task->suggested_preset_key ?? ''));
                    $assignee = $presetLabel !== '' ? 'Sin sugerencia fija / '.$presetLabel : 'Sin sugerencia fija';
                }

                return [
                    'business' => (string) ($tenant?->name ?? 'Negocio sin nombre'),
                    'playbook' => (string) $task->title,
                    'queue' => (string) $task->queue_label,
                    'owner' => $this->internalOwnerLabel($task),
                    'assignee' => $assignee,
                    'follow_up' => (string) $task->follow_up_status_label,
                    'next_touch' => $this->nextTouchLabel($task),
                    'sla' => $this->formatSla($task),
                    'tone' => $this->playbookTone($task),
                    'href' => $this->taskAdminHref($task),
                ];
            })
            ->values()
            ->all();
    }

    private function loadRecentPayments(): void
    {
        try {
            $this->recentPayments = SubscriptionPayment::query()
                ->withoutGlobalScopes()
                ->with('tenant')
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->map(function (SubscriptionPayment $payment): array {
                    $status = match ((string) $payment->status) {
                        SubscriptionPayment::STATUS_PENDING => 'Pendiente',
                        SubscriptionPayment::STATUS_APPROVED => 'Aprobado',
                        SubscriptionPayment::STATUS_REJECTED => 'Rechazado',
                        default => 'Desconocido',
                    };

                    $tone = match ((string) $payment->status) {
                        SubscriptionPayment::STATUS_PENDING => 'warning',
                        SubscriptionPayment::STATUS_APPROVED => 'success',
                        SubscriptionPayment::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    };

                    return [
                        'business' => (string) ($payment->tenant?->name ?? 'Negocio sin registro'),
                        'reference' => (string) ($payment->reference_number ?? 'Sin referencia'),
                        'amount' => 'L. '.number_format((float) $payment->amount, 2),
                        'status' => $status,
                        'tone' => $tone,
                        'reported_at' => optional($payment->created_at)?->format('d/m/Y H:i') ?? 'Sin fecha',
                    ];
                })
                ->all();
        } catch (\Throwable) {
            $this->recentPayments = [];
        }
    }

    /**
     * @return array{label: string, count: int, overdue: int, unassigned: int, copy: string, href: string, tone: string}
     */
    private function buildLaneCard(
        Collection $tasks,
        string $queue,
        string $label,
        string $copy,
        string $href,
    ): array {
        $laneTasks = $tasks->filter(fn (OperationalPlaybookTask $task): bool => (string) $task->queue === $queue);
        $count = $laneTasks->count();
        $overdue = $laneTasks->filter(fn (OperationalPlaybookTask $task): bool => $task->isOverdue())->count();
        $unassigned = $laneTasks->filter(fn (OperationalPlaybookTask $task): bool => blank($task->admin_owner_id))->count();

        return [
            'label' => $label,
            'count' => $count,
            'overdue' => $overdue,
            'unassigned' => $unassigned,
            'copy' => $copy,
            'href' => $href,
            'tone' => $overdue > 0 ? 'danger' : (($count > 0 || $unassigned > 0) ? 'warning' : 'success'),
        ];
    }

    /**
     * @return array{task: string, business: string, owner: string, internal_owner: string, assignee: string, follow_up: string, next_touch: string, queue: string, sla: string, reason: string, tone: string, href: string}
     */
    private function mapWorkboardTask(OperationalPlaybookTask $task): array
    {
        $tenant = $task->tenant;
        $businessAssignee = $task->assignee?->name;

        if ($businessAssignee === null || $businessAssignee === '') {
            $presetLabel = $this->presetLabel((string) ($task->suggested_preset_key ?? ''));
            $businessAssignee = $presetLabel !== '' ? 'Sugerido negocio: '.$presetLabel : 'Sin sugerencia de negocio';
        }

        return [
            'task' => (string) $task->title,
            'business' => (string) ($tenant?->name ?? 'Negocio sin nombre'),
            'owner' => $this->tenantOwnerLabel($tenant?->user),
            'internal_owner' => $this->internalOwnerLabel($task),
            'assignee' => $businessAssignee,
            'follow_up' => (string) $task->follow_up_status_label,
            'next_touch' => $this->nextTouchLabel($task),
            'queue' => (string) $task->queue_label,
            'sla' => $this->formatSla($task),
            'reason' => $this->taskReason($task),
            'tone' => $this->playbookTone($task),
            'href' => $this->taskAdminHref($task),
        ];
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

    /**
     * @param  callable(SubscriptionPayment): bool  $filter
     */
    private function convertedTenantCount(callable $filter): int
    {
        return $this->monthlyProConversionCollection()
            ->filter($filter)
            ->map(fn (SubscriptionPayment $payment): string => (string) $payment->tenant_id)
            ->filter()
            ->unique()
            ->count();
    }

    private function conversationRevenueValue(CustomerConversation $conversation): float
    {
        $profile = $conversation->customerProfile;

        if (! $profile instanceof \App\Models\CustomerProfile) {
            return 0.0;
        }

        $firstConversion = (float) ($profile->first_conversion_value ?? 0);

        if ($firstConversion > 0) {
            return $firstConversion;
        }

        $initialLtv = (float) ($profile->initial_ltv ?? 0);

        if ($initialLtv > 0) {
            return $initialLtv;
        }

        return (float) ($profile->total_spent ?? 0);
    }

    private function conversationHasJourneyAssistance(CustomerConversation $conversation): bool
    {
        $signals = (array) data_get($conversation->metadata, 'business_signals', []);

        if ($signals !== []) {
            return true;
        }

        return $conversation->source_channel !== null && $conversation->source_channel !== '';
    }

    /**
     * @param  Collection<int, CustomerTouchpoint>  $touchpoints
     * @return array<string, array{label:string,revenue:float,vertical:string,owners:string,channels:string,conversations:int}>
     */
    private function buildConversationPathShares(Collection $touchpoints): array
    {
        $shares = [];

        $touchpoints
            ->groupBy(fn (CustomerTouchpoint $touchpoint): string => (string) ($touchpoint->customer_conversation_id ?? ''))
            ->each(function (Collection $items) use (&$shares): void {
                $conversation = $items->first()?->conversation;

                if (! $conversation instanceof CustomerConversation || $conversation->status !== 'closed') {
                    return;
                }

                $path = $items
                    ->sortBy('id')
                    ->map(fn (CustomerTouchpoint $touchpoint): string => (string) data_get($touchpoint->metadata, 'journey_sequence_label', data_get($touchpoint->metadata, 'journey_step_title', $touchpoint->template_label)))
                    ->filter()
                    ->unique()
                    ->values();

                if ($path->isEmpty()) {
                    return;
                }

                $tenant = $conversation->customerProfile?->tenant;
                $label = $path->implode(' -> ');
                $channels = $items->pluck('channel')->filter()->unique()->map(fn (string $channel): string => Str::of($channel)->replace('_', ' ')->title()->value())->implode(', ');
                $owner = $conversation->owner?->name ?? 'Sin owner';
                $segmentLabel = $tenant instanceof Tenant ? $this->segmentLabel($this->segmentKeyForTenant($tenant)) : 'General';

                $shares[$label] ??= [
                    'label' => $label,
                    'revenue' => 0.0,
                    'vertical' => $segmentLabel,
                    'owners' => $owner,
                    'channels' => $channels !== '' ? $channels : 'CRM',
                    'conversations' => 0,
                ];

                $shares[$label]['revenue'] += $this->conversationRevenueValue($conversation);
                $shares[$label]['conversations']++;
            });

        return $shares;
    }

    private function topConversationPathLabel(Collection $touchpoints): string
    {
        $shares = collect($this->buildConversationPathShares($touchpoints));

        if ($shares->isEmpty()) {
            return 'Sin ruta dominante';
        }

        $top = $shares->sortByDesc(fn (array $share): float => ((float) ($share['revenue'] ?? 0.0) * 1000) + (int) ($share['conversations'] ?? 0))->first();

        return is_array($top) ? (string) ($top['label'] ?? 'Sin ruta dominante') : 'Sin ruta dominante';
    }

    private function playbookTone(OperationalPlaybookTask $task): string
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

    private function revenuePlaybookTone(OperationalPlaybookTask $task, string $conversionSignal): string
    {
        if ((string) $task->queue === 'growth' && str_contains($conversionSignal, 'PRO este mes')) {
            return 'success';
        }

        if ((string) $task->queue === 'growth' && str_contains($conversionSignal, 'Sin conversiones PRO')) {
            return 'warning';
        }

        return $this->playbookTone($task);
    }

    private function formatSla(OperationalPlaybookTask $task): string
    {
        if (! $task->due_at instanceof Carbon) {
            return 'Sin SLA';
        }

        if ($task->isOverdue()) {
            return 'SLA vencido';
        }

        return 'Vence '.$task->due_at->diffForHumans();
    }

    private function nextTouchLabel(OperationalPlaybookTask $task): string
    {
        if (! $task->next_follow_up_at instanceof Carbon) {
            return 'Sin siguiente toque';
        }

        if ($task->next_follow_up_at->isPast()) {
            return 'Toque atrasado '.$task->next_follow_up_at->diffForHumans();
        }

        return 'Siguiente '.$task->next_follow_up_at->diffForHumans();
    }

    private function presetLabel(string $presetKey): string
    {
        if ($presetKey === '') {
            return '';
        }

        return Str::of($presetKey)->replace('_', ' ')->title()->value();
    }

    private function tenantOwnerLabel(?User $user): string
    {
        if (! $user instanceof User) {
            return 'Sin propietario';
        }

        return (string) ($user->email ?: $user->name);
    }

    private function internalOwnerLabel(OperationalPlaybookTask $task): string
    {
        if ($task->adminOwner instanceof User) {
            return (string) ($task->adminOwner->name ?: $task->adminOwner->email);
        }

        if ($task->tenant?->crmOwner instanceof User) {
            return (string) ($task->tenant->crmOwner->name ?: $task->tenant->crmOwner->email).' / cartera';
        }

        $presetLabel = $this->presetLabel((string) ($task->suggested_preset_key ?? ''));

        return $presetLabel !== '' ? 'Sin owner / '.$presetLabel : 'Sin owner interno';
    }

    private function taskReason(OperationalPlaybookTask $task): string
    {
        $latestNote = trim((string) Str::of((string) ($task->internal_notes ?? ''))->before("\n"));

        if ($latestNote !== '') {
            return $latestNote;
        }

        return (string) ($task->summary ?? 'Frente operativo listo para mover hoy.');
    }

    private function ownerNeedsRevenueMotion(Tenant $tenant): bool
    {
        return $this->needsActivationPush($tenant)
            || $this->isExpansionReady($tenant)
            || $this->isRenewalMotionCandidate($tenant)
            || (int) ($tenant->crm_watch_score ?? 0) > 0;
    }

    private function segmentKeyForPayment(SubscriptionPayment $payment): string
    {
        return $payment->tenant instanceof Tenant
            ? $this->segmentKeyForTenant($payment->tenant)
            : 'retail_general';
    }

    private function segmentKeyForTenant(Tenant $tenant): string
    {
        $segmentKey = (string) ($tenant->getRawOriginal('business_segment_key') ?? '');

        return $segmentKey !== '' ? $segmentKey : 'retail_general';
    }

    private function segmentLabel(string $segmentKey): string
    {
        $label = config("business-segments.profiles.{$segmentKey}.label");

        return is_string($label) && $label !== ''
            ? $label
            : Str::of($segmentKey === '' ? 'general' : $segmentKey)->replace('_', ' ')->title()->value();
    }

    private function planKeyForTenant(Tenant $tenant): string
    {
        $planKey = (string) ($tenant->getRawOriginal('plan_type') ?? '');

        if ($planKey !== '') {
            return $planKey;
        }

        return $tenant->is_pro ? 'pro' : Tenant::PLAN_SEMILLA;
    }

    private function planKeyForPayment(SubscriptionPayment $payment): string
    {
        $originPlan = (string) ($payment->plan_before_upgrade ?? '');

        if ($originPlan !== '') {
            return $originPlan;
        }

        return $payment->tenant instanceof Tenant
            ? $this->planKeyForTenant($payment->tenant)
            : 'pro';
    }

    private function planLabel(string $planKey): string
    {
        return match ($planKey) {
            'pro' => 'PRO',
            Tenant::PLAN_CRECIMIENTO => 'Crecimiento',
            Tenant::PLAN_COSECHA => 'Cosecha',
            default => 'Semilla',
        };
    }

    private function cityLabelForPayment(SubscriptionPayment $payment): string
    {
        return (string) ($payment->tenant?->city?->name ?: 'Sin ciudad');
    }

    private function ownerKeyForPayment(SubscriptionPayment $payment): string
    {
        return (string) ($payment->tenant?->crm_owner_id ?: 'unassigned');
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

    private function closingChannelKey(Tenant $tenant): string
    {
        $signals = collect([
            filled($tenant->whatsapp_number) ? 'whatsapp' : null,
            filled($tenant->website_url) ? 'website' : null,
            filled($tenant->phone) ? 'phone' : null,
        ])->filter()->values();

        if ($signals->count() >= 2) {
            return 'multi';
        }

        return (string) ($signals->first() ?? 'none');
    }

    private function closingChannelLabel(string $channelKey): string
    {
        $label = config("revenue-ops.channels.{$channelKey}.label");

        return is_string($label) && $label !== ''
            ? $label
            : Str::of($channelKey === '' ? 'none' : $channelKey)->replace('_', ' ')->title()->value();
    }

    private function closingChannelCopy(string $channelKey): string
    {
        $copy = config("revenue-ops.channels.{$channelKey}.copy");

        return is_string($copy) && $copy !== ''
            ? $copy
            : 'Sin lectura adicional para este canal.';
    }

    private function revenuePlaybookConversionSignal(OperationalPlaybookTask $task): string
    {
        if ((string) $task->queue !== 'growth') {
            return 'Playbook operativo para mover ingresos, continuidad o activacion.';
        }

        $segmentKey = (string) ($task->segment_key ?? '');
        $segmentLabel = $this->segmentLabel($segmentKey);
        $segmentConversions = $this->convertedTenantCount(
            fn (SubscriptionPayment $payment): bool => $this->segmentKeyForPayment($payment) === $segmentKey
        );
        $ownerKey = (string) ($task->admin_owner_id ?? $task->tenant?->crm_owner_id ?? 'unassigned');
        $ownerConversions = $this->convertedTenantCount(
            fn (SubscriptionPayment $payment): bool => $this->ownerKeyForPayment($payment) === $ownerKey
        );

        if ($segmentConversions > 0 && $ownerConversions > 0) {
            return $segmentConversions.' PRO este mes en '.$segmentLabel.' / owner con '.$ownerConversions.' cierres.';
        }

        if ($segmentConversions > 0) {
            return $segmentConversions.' PRO este mes en '.$segmentLabel.'. Usa esa senal para priorizar el upsell.';
        }

        if ($ownerConversions > 0) {
            return 'Owner con '.$ownerConversions.' conversiones PRO este mes. Conviene acelerar este playbook.';
        }

        return 'Sin conversiones PRO este mes en este vertical. Valida propuesta antes de empujar expansion.';
    }

    private function taskAdminHref(OperationalPlaybookTask $task): string
    {
        if ($this->operationsUrl !== '#') {
            return $this->operationsUrl.'?tableSearch='.urlencode((string) $task->title);
        }

        $tenant = $task->tenant;

        return $tenant instanceof Tenant
            ? $this->safeRoute('filament.admin.resources.tenants.edit', ['record' => $tenant])
            : $this->tenantsUrl;
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
