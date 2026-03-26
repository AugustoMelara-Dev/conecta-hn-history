<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\TenantGovernance\Services\SuperAdminPortfolioReader;
use App\Domain\TenantGovernance\Services\SuperAdminJourneyAnalyticsReader;
use App\Domain\TenantGovernance\Services\SuperAdminOperationsReadModel;
use App\Domain\TenantGovernance\Services\SuperAdminRevenueInsightsReader;
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
            $reader = app(SuperAdminPortfolioReader::class);
            $metrics = $reader->metrics($this->portfolioCollection(), $this->monthlyProConversionCollection());

            $this->tenantsCount = $metrics['tenantsCount'];
            $this->proTenantsCount = $metrics['proTenantsCount'];
            $this->proConversionsThisMonthCount = $metrics['proConversionsThisMonthCount'];
            $this->pendingTenantsCount = $metrics['pendingTenantsCount'];
            $this->suspendedTenantsCount = $metrics['suspendedTenantsCount'];
            $this->catalogGapCount = $metrics['catalogGapCount'];
            $this->expiringProCount = $metrics['expiringProCount'];
            $this->crmWatchlistCount = $metrics['crmWatchlistCount'];
            $this->atRiskTenantsCount = $metrics['atRiskTenantsCount'];
            $this->crmOverdueAccountsCount = $metrics['crmOverdueAccountsCount'];
            $this->crmFollowupsTodayCount = $metrics['crmFollowupsTodayCount'];
            $this->crmOwnedAccountsCount = $metrics['crmOwnedAccountsCount'];
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
            $reader = app(SuperAdminPortfolioReader::class);
            $this->portfolioTenants = $reader->portfolioTenants();
            $this->usersCount = $reader->usersCount();
            $this->pendingPaymentsCount = $reader->pendingPaymentsCount();
        } catch (\Throwable $exception) {
            report($exception);
            $this->portfolioTenants = [];
        }
    }

    private function loadMonthlyProConversions(): void
    {
        try {
            $this->monthlyProConversions = app(SuperAdminPortfolioReader::class)->monthlyProConversions();
        } catch (\Throwable $exception) {
            report($exception);
            $this->monthlyProConversions = [];
        }
    }

    private function loadGlobalPlaybookState(): void
    {
        try {
            $reader = app(SuperAdminPortfolioReader::class);
            $this->activePlaybookTasks = $reader->activePlaybookTasks();
            $tasks = collect($this->activePlaybookTasks);
            $counts = $reader->playbookCounts($tasks);

            $this->activePlaybookCount = $counts['activePlaybookCount'];
            $this->overduePlaybookCount = $counts['overduePlaybookCount'];
            $this->unassignedPlaybookCount = $counts['unassignedPlaybookCount'];
            $this->commercialFollowupCount = $counts['commercialFollowupCount'];
            $this->renewalPlaybookCount = $counts['renewalPlaybookCount'];
            $this->activationPlaybookCount = $counts['activationPlaybookCount'];
            $this->growthPlaybookCount = $counts['growthPlaybookCount'];

            $this->operationalLanes = [
                $this->buildLaneCard($tasks, 'activation', 'Activacion', 'Onboarding comercial, oferta visible y primer valor entregado.', $this->operationsUrl),
                $this->buildLaneCard($tasks, 'growth', 'Expansion', 'Upsell, plan PRO y crecimiento sobre cuentas con base sana.', $this->operationsUrl),
                $this->buildLaneCard($tasks, 'leads', 'Captacion', 'Leads nuevos y primeras respuestas comerciales.', $this->operationsUrl),
                $this->buildLaneCard($tasks, 'appointments', 'Agenda', 'Reservas, confirmaciones y prevencion de no-show.', $this->operationsUrl),
                $this->buildLaneCard($tasks, 'retention', 'Retencion', 'Abandono, recompra y continuidad de clientes.', $this->operationsUrl),
                $this->buildLaneCard($tasks, 'billing', 'Renovacion', 'Cobro, continuidad de plan y churn prevention.', $this->operationsUrl),
            ];
        } catch (\Throwable $exception) {
            report($exception);
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
        /** @var SuperAdminOperationsReadModel $reader */
        $reader = app(SuperAdminOperationsReadModel::class);
        $this->globalWorkboard = $reader->globalWorkboard(
            $this->activePlaybookCollection(),
            $this->operationsUrl,
            $this->tenantsUrl,
            $this->safeRoute(...),
        );
    }

    private function loadAssigneeLoadBoard(): void
    {
        $this->assigneeLoad = app(SuperAdminOperationsReadModel::class)
            ->assigneeLoad($this->activePlaybookCollection(), $this->operationsUrl);
    }

    private function loadOwnerPortfolio(): void
    {
        $this->ownerPortfolio = app(SuperAdminOperationsReadModel::class)
            ->ownerPortfolio($this->portfolioCollection(), $this->operationsUrl);
    }

    private function loadTeamPortfolio(): void
    {
        $this->teamPortfolio = app(SuperAdminOperationsReadModel::class)
            ->teamPortfolio($this->portfolioCollection(), $this->operationsUrl);
    }

    private function loadSlaBacklog(): void
    {
        $this->slaBacklog = app(SuperAdminOperationsReadModel::class)
            ->slaBacklog($this->portfolioCollection(), $this->safeRoute(...));
    }

    private function loadCommercialFunnel(): void
    {
        $this->commercialFunnel = app(SuperAdminOperationsReadModel::class)
            ->commercialFunnel($this->portfolioCollection(), $this->tenantsCount, $this->tenantsUrl);
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
        $this->verticalMonthlyGoals = app(SuperAdminRevenueInsightsReader::class)
            ->verticalMonthlyGoals($this->portfolioCollection(), $this->monthlyProConversionCollection(), $this->billingUrl);
    }

    private function loadActivationCohorts(): void
    {
        $this->activationCohorts = app(SuperAdminRevenueInsightsReader::class)
            ->activationCohorts($this->portfolioCollection(), $this->tenantsUrl);
    }

    private function loadPlanCohorts(): void
    {
        $this->planCohorts = app(SuperAdminRevenueInsightsReader::class)
            ->planCohorts($this->portfolioCollection(), $this->monthlyProConversionCollection(), $this->billingUrl);
    }

    private function loadCityCohorts(): void
    {
        $this->cityCohorts = app(SuperAdminRevenueInsightsReader::class)
            ->cityCohorts($this->portfolioCollection(), $this->monthlyProConversionCollection(), $this->tenantsUrl);
    }

    private function loadChannelCohorts(): void
    {
        $this->channelCohorts = app(SuperAdminRevenueInsightsReader::class)
            ->channelCohorts($this->portfolioCollection(), $this->tenantsUrl);
    }

    private function loadSegmentExpansion(): void
    {
        $this->segmentExpansion = app(SuperAdminRevenueInsightsReader::class)
            ->segmentExpansion($this->portfolioCollection(), $this->monthlyProConversionCollection(), $this->tenantsUrl);
    }

    private function loadOwnerCohorts(): void
    {
        $this->ownerCohorts = app(SuperAdminRevenueInsightsReader::class)
            ->ownerCohorts($this->portfolioCollection(), $this->monthlyProConversionCollection(), $this->operationsUrl);
    }

    private function loadVerticalChurn(): void
    {
        $this->verticalChurn = app(SuperAdminRevenueInsightsReader::class)
            ->verticalChurn($this->portfolioCollection(), $this->tenantsUrl);
    }

    private function loadVerticalJourneyUplift(): void
    {
        /** @var SuperAdminJourneyAnalyticsReader $reader */
        $reader = app(SuperAdminJourneyAnalyticsReader::class);
        $this->verticalJourneyUplift = $reader->verticalJourneyUplift($this->operationsUrl);
    }

    private function loadRevenuePlaybooks(): void
    {
        $this->revenuePlaybooks = app(SuperAdminOperationsReadModel::class)->revenuePlaybooks(
            $this->activePlaybookCollection(),
            $this->monthlyProConversionCollection(),
            $this->operationsUrl,
            $this->tenantsUrl,
            $this->safeRoute(...),
        );
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
            $this->churnWatchlist = app(SuperAdminOperationsReadModel::class)
                ->churnWatchlist($this->portfolioCollection(), $this->safeRoute(...));
        } catch (\Throwable) {
            $this->churnWatchlist = [];
        }
    }

    private function loadCommercialFollowups(): void
    {
        $this->commercialFollowups = app(SuperAdminOperationsReadModel::class)->commercialFollowups(
            $this->activePlaybookCollection(),
            $this->operationsUrl,
            $this->tenantsUrl,
            $this->safeRoute(...),
        );
    }

    private function loadRecentPayments(): void
    {
        try {
            $this->recentPayments = app(SuperAdminPortfolioReader::class)->recentPayments();
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
