<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\SuperAdmin;

use App\Models\OperationalPlaybookTask;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class SuperAdminDashboardAssembler
{
    public function __construct(
        private readonly SuperAdminOperationsReadModel $operations,
        private readonly SuperAdminJourneyAnalyticsReader $journeyAnalytics,
        private readonly SuperAdminRevenueInsightsReader $revenueInsights,
        private readonly SuperAdminTenantPlanReader $planReader,
    ) {}

    /**
     * @param  Collection<int, OperationalPlaybookTask>  $tasks
     * @param  Collection<int, Tenant>  $portfolio
     * @param  Collection<int, SubscriptionPayment>  $monthlyProConversions
     * @return array<string, mixed>
     */
    public function build(array $counts, Collection $tasks, Collection $portfolio, Collection $monthlyProConversions, array $urls, callable $safeRoute): array
    {
        return [
            'openAttentionCount' => $this->openAttentionCount($counts),
            'attentionQueues' => $this->attentionQueues($counts, $urls),
            'operationalLanes' => $this->operationalLanes($tasks, (string) ($urls['operations'] ?? '#')),
            'globalWorkboard' => $this->operations->globalWorkboard($tasks, (string) ($urls['operations'] ?? '#'), (string) ($urls['tenants'] ?? '#'), $safeRoute),
            'assigneeLoad' => $this->operations->assigneeLoad($tasks, (string) ($urls['operations'] ?? '#')),
            'ownerPortfolio' => $this->operations->ownerPortfolio($portfolio, (string) ($urls['operations'] ?? '#')),
            'teamPortfolio' => $this->operations->teamPortfolio($portfolio, (string) ($urls['operations'] ?? '#')),
            'slaBacklog' => $this->operations->slaBacklog($portfolio, $safeRoute),
            'commercialFunnel' => $this->operations->commercialFunnel($portfolio, (int) ($counts['tenantsCount'] ?? 0), (string) ($urls['tenants'] ?? '#')),
            'ownerGoals' => $this->ownerGoals($portfolio, $tasks, (string) ($urls['operations'] ?? '#')),
            'teamGoals' => $this->teamGoals($portfolio, $tasks, (string) ($urls['operations'] ?? '#')),
            'verticalMonthlyGoals' => $this->revenueInsights->verticalMonthlyGoals($portfolio, $monthlyProConversions, (string) ($urls['billing'] ?? '#')),
            'activationCohorts' => $this->revenueInsights->activationCohorts($portfolio, (string) ($urls['tenants'] ?? '#')),
            'planCohorts' => $this->revenueInsights->planCohorts($portfolio, $monthlyProConversions, (string) ($urls['billing'] ?? '#')),
            'cityCohorts' => $this->revenueInsights->cityCohorts($portfolio, $monthlyProConversions, (string) ($urls['tenants'] ?? '#')),
            'channelCohorts' => $this->revenueInsights->channelCohorts($portfolio, (string) ($urls['tenants'] ?? '#')),
            'ownerCohorts' => $this->revenueInsights->ownerCohorts($portfolio, $monthlyProConversions, (string) ($urls['operations'] ?? '#')),
            'segmentExpansion' => $this->revenueInsights->segmentExpansion($portfolio, $monthlyProConversions, (string) ($urls['tenants'] ?? '#')),
            'verticalChurn' => $this->revenueInsights->verticalChurn($portfolio, (string) ($urls['tenants'] ?? '#')),
            'verticalJourneyUplift' => $this->journeyAnalytics->verticalJourneyUplift((string) ($urls['operations'] ?? '#')),
            'revenuePlaybooks' => $this->operations->revenuePlaybooks($tasks, $monthlyProConversions, (string) ($urls['operations'] ?? '#'), (string) ($urls['tenants'] ?? '#'), $safeRoute),
            'networkJourneyPaths' => $this->journeyAnalytics->networkJourneyPaths((string) ($urls['operations'] ?? '#')),
            'churnWatchlist' => $this->operations->churnWatchlist($portfolio, $safeRoute),
            'commercialFollowups' => $this->operations->commercialFollowups($tasks, (string) ($urls['operations'] ?? '#'), (string) ($urls['tenants'] ?? '#'), $safeRoute),
        ];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function openAttentionCount(array $counts): int
    {
        return (int) ($counts['pendingPaymentsCount'] ?? 0)
            + (int) ($counts['pendingTenantsCount'] ?? 0)
            + (int) ($counts['unassignedPlaybookCount'] ?? 0)
            + (int) ($counts['overduePlaybookCount'] ?? 0)
            + (int) ($counts['crmOverdueAccountsCount'] ?? 0)
            + (int) ($counts['commercialFollowupCount'] ?? 0)
            + (int) ($counts['activationPlaybookCount'] ?? 0)
            + (int) ($counts['growthPlaybookCount'] ?? 0)
            + (int) ($counts['expiringProCount'] ?? 0)
            + (int) ($counts['crmWatchlistCount'] ?? 0);
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{label:string,count:int,copy:string,href:string,tone:string}>
     */
    private function attentionQueues(array $counts, array $urls): array
    {
        return [
            [
                'label' => 'Pagos por validar',
                'count' => (int) ($counts['pendingPaymentsCount'] ?? 0),
                'copy' => 'Comprobantes listos para revision antes de activar o rechazar PRO.',
                'href' => (string) ($urls['payments'] ?? '#'),
                'tone' => (int) ($counts['pendingPaymentsCount'] ?? 0) > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Altas pendientes',
                'count' => (int) ($counts['pendingTenantsCount'] ?? 0),
                'copy' => 'Negocios nuevos que todavia necesitan aprobacion operativa.',
                'href' => (string) ($urls['tenants'] ?? '#'),
                'tone' => (int) ($counts['pendingTenantsCount'] ?? 0) > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'SLA vencido',
                'count' => (int) ($counts['overduePlaybookCount'] ?? 0),
                'copy' => 'Playbooks ya vencidos que deberían estar en manos de alguien hoy.',
                'href' => (string) ($urls['operations'] ?? '#'),
                'tone' => (int) ($counts['overduePlaybookCount'] ?? 0) > 0 ? 'danger' : 'success',
            ],
            [
                'label' => 'Sin owner interno',
                'count' => (int) ($counts['unassignedPlaybookCount'] ?? 0),
                'copy' => 'Casos sin responsable comercial interno y con riesgo de quedarse en el aire.',
                'href' => (string) ($urls['operations'] ?? '#'),
                'tone' => (int) ($counts['unassignedPlaybookCount'] ?? 0) > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Cartera vencida',
                'count' => (int) ($counts['crmOverdueAccountsCount'] ?? 0),
                'copy' => 'Negocios con siguiente toque atrasado y riesgo de enfriarse por falta de disciplina.',
                'href' => (string) ($urls['tenants'] ?? '#'),
                'tone' => (int) ($counts['crmOverdueAccountsCount'] ?? 0) > 0 ? 'danger' : 'success',
            ],
            [
                'label' => 'Seguimiento comercial',
                'count' => (int) ($counts['commercialFollowupCount'] ?? 0),
                'copy' => 'Leads, carritos y recompras que siguen vivos entre tenants.',
                'href' => (string) ($urls['operations'] ?? '#'),
                'tone' => (int) ($counts['commercialFollowupCount'] ?? 0) > 0 ? 'info' : 'success',
            ],
            [
                'label' => 'Activacion comercial',
                'count' => (int) ($counts['activationPlaybookCount'] ?? 0),
                'copy' => 'Negocios nuevos que todavia no terminan de activar oferta, canal o confianza.',
                'href' => (string) ($urls['operations'] ?? '#'),
                'tone' => (int) ($counts['activationPlaybookCount'] ?? 0) > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Expansion lista',
                'count' => (int) ($counts['growthPlaybookCount'] ?? 0),
                'copy' => 'Cuentas con base real para mover upgrade, plan PRO o growth comercial.',
                'href' => (string) ($urls['operations'] ?? '#'),
                'tone' => (int) ($counts['growthPlaybookCount'] ?? 0) > 0 ? 'info' : 'success',
            ],
            [
                'label' => 'PRO por vencer',
                'count' => (int) ($counts['expiringProCount'] ?? 0),
                'copy' => 'Renovaciones cercanas que merecen seguimiento antes del corte.',
                'href' => (string) ($urls['billing'] ?? '#'),
                'tone' => (int) ($counts['expiringProCount'] ?? 0) > 0 ? 'danger' : 'success',
            ],
            [
                'label' => 'Churn en observacion',
                'count' => (int) ($counts['crmWatchlistCount'] ?? 0),
                'copy' => 'Cuentas persistidas en watchlist con motivo, tono y fecha de alerta.',
                'href' => (string) ($urls['tenants'] ?? '#'),
                'tone' => (int) ($counts['crmWatchlistCount'] ?? 0) > 0 ? 'warning' : 'success',
            ],
        ];
    }

    /**
     * @param  Collection<int, OperationalPlaybookTask>  $tasks
     * @return list<array{label:string,count:int,overdue:int,unassigned:int,copy:string,href:string,tone:string}>
     */
    private function operationalLanes(Collection $tasks, string $operationsUrl): array
    {
        return [
            $this->laneCard($tasks, 'activation', 'Activacion', 'Onboarding comercial, oferta visible y primer valor entregado.', $operationsUrl),
            $this->laneCard($tasks, 'growth', 'Expansion', 'Upsell, plan PRO y crecimiento sobre cuentas con base sana.', $operationsUrl),
            $this->laneCard($tasks, 'leads', 'Captacion', 'Leads nuevos y primeras respuestas comerciales.', $operationsUrl),
            $this->laneCard($tasks, 'appointments', 'Agenda', 'Reservas, confirmaciones y prevencion de no-show.', $operationsUrl),
            $this->laneCard($tasks, 'retention', 'Retencion', 'Abandono, recompra y continuidad de clientes.', $operationsUrl),
            $this->laneCard($tasks, 'billing', 'Renovacion', 'Cobro, continuidad de plan y churn prevention.', $operationsUrl),
        ];
    }

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @param  Collection<int, OperationalPlaybookTask>  $tasks
     * @return list<array{owner:string,accounts:int,goal:int,covered:int,gap:int,coverage:string,mix:string,tone:string,href:string}>
     */
    private function ownerGoals(Collection $portfolio, Collection $tasks, string $operationsUrl): array
    {
        return $portfolio
            ->filter(fn (Tenant $tenant): bool => $this->ownerNeedsRevenueMotion($tenant))
            ->groupBy(fn (Tenant $tenant): string => (string) ($tenant->crm_owner_id ?: 'unassigned'))
            ->map(function (Collection $tenants, string $ownerKey) use ($tasks, $operationsUrl): array {
                $activation = $tenants->filter(fn (Tenant $tenant): bool => $this->needsActivationPush($tenant))->count();
                $growth = $tenants->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count();
                $watch = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $renewal = $tenants->filter(fn (Tenant $tenant): bool => $this->isRenewalMotionCandidate($tenant))->count();
                $goal = $activation + $growth + $watch + $renewal;
                $covered = $tasks->filter(function (OperationalPlaybookTask $task) use ($ownerKey): bool {
                    $tenant = $task->tenant;
                    $tenant = $tenant instanceof Tenant ? $tenant : null;
                    $resolvedOwnerKey = (string) ($task->admin_owner_id ?? ($tenant instanceof Tenant ? $tenant->crm_owner_id : '') ?? '');

                    if ($ownerKey === 'unassigned') {
                        return $resolvedOwnerKey === '' && in_array((string) $task->queue, ['activation', 'growth', 'billing', 'retention'], true);
                    }

                    return $resolvedOwnerKey === $ownerKey && in_array((string) $task->queue, ['activation', 'growth', 'billing', 'retention'], true);
                })->count();
                $gap = max(0, $goal - $covered);
                /** @var Tenant|null $firstTenant */
                $firstTenant = $tenants->first();
                $crmOwner = $firstTenant?->crmOwner;
                $ownerName = $ownerKey === 'unassigned'
                    ? 'Sin owner interno'
                    : ($crmOwner instanceof \App\Models\User
                        ? (string) ($crmOwner->name ?: $crmOwner->email)
                        : 'Owner interno');

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
                    'href' => $operationsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['gap'] * 100) + ($item['goal'] * 10) + $item['accounts'])
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @param  Collection<int, OperationalPlaybookTask>  $tasks
     * @return list<array{team:string,portfolio:int,goal:int,covered:int,gap:int,focus:string,tone:string,href:string}>
     */
    private function teamGoals(Collection $portfolio, Collection $tasks, string $operationsUrl): array
    {
        /** @var array<string, array{label?: string, focus?: string, queues?: array<int, string>}> $teams */
        $teams = config('revenue-ops.teams', []);

        return collect($teams)
            ->map(function (array $teamConfig, string $team) use ($portfolio, $tasks, $operationsUrl): array {
                $goal = $this->revenueGoalForTeam($portfolio, $team);
                $queues = array_values(array_filter((array) ($teamConfig['queues'] ?? []), static fn (mixed $value): bool => $value !== ''));
                $covered = $tasks->filter(fn (OperationalPlaybookTask $task): bool => in_array((string) $task->queue, $queues, true))->count();
                $teamPortfolio = $portfolio->filter(fn (Tenant $tenant): bool => (string) ($tenant->getRawOriginal('crm_team') ?? Tenant::CRM_TEAM_ACTIVATION) === $team)->count();
                $gap = max(0, $goal - $covered);

                return [
                    'team' => (string) ($teamConfig['label'] ?? str($team)->replace('_', ' ')->title()->value()),
                    'portfolio' => $teamPortfolio,
                    'goal' => $goal,
                    'covered' => $covered,
                    'gap' => $gap,
                    'focus' => (string) ($teamConfig['focus'] ?? 'Sin foco configurado.'),
                    'tone' => $gap > 0 ? (($team === Tenant::CRM_TEAM_BILLING || $team === Tenant::CRM_TEAM_RETENTION) ? 'danger' : 'warning') : 'success',
                    'href' => $operationsUrl,
                ];
            })
            ->filter(fn (array $item): bool => $item['goal'] > 0 || $item['portfolio'] > 0 || $item['covered'] > 0)
            ->sortByDesc(fn (array $item): int => ($item['gap'] * 100) + ($item['goal'] * 10) + $item['portfolio'])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, OperationalPlaybookTask>  $tasks
     * @return array{label:string,count:int,overdue:int,unassigned:int,copy:string,href:string,tone:string}
     */
    private function laneCard(Collection $tasks, string $queue, string $label, string $copy, string $href): array
    {
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
     * @param  Collection<int, Tenant>  $portfolio
     */
    private function revenueGoalForTeam(Collection $portfolio, string $team): int
    {
        return match ($team) {
            Tenant::CRM_TEAM_ACTIVATION => $portfolio->filter(fn (Tenant $tenant): bool => $this->needsActivationPush($tenant))->count(),
            Tenant::CRM_TEAM_GROWTH => $portfolio->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count(),
            Tenant::CRM_TEAM_BILLING => $portfolio->filter(fn (Tenant $tenant): bool => $this->isRenewalMotionCandidate($tenant))->count(),
            Tenant::CRM_TEAM_RETENTION => $portfolio->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count(),
            default => 0,
        };
    }

    private function ownerNeedsRevenueMotion(Tenant $tenant): bool
    {
        return $this->needsActivationPush($tenant)
            || $this->isExpansionReady($tenant)
            || $this->isRenewalMotionCandidate($tenant)
            || (int) ($tenant->crm_watch_score ?? 0) > 0;
    }

    private function needsActivationPush(Tenant $tenant): bool
    {
        $crmStatus = (string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW);
        $daysSinceCreated = $tenant->created_at?->diffInDays(now()) ?? 999;

        return in_array($crmStatus, [Tenant::CRM_STATUS_NEW, Tenant::CRM_STATUS_ONBOARDING], true)
            || (((int) ($tenant->products_total ?? 0) === 0 && (int) ($tenant->services_total ?? 0) === 0) && $daysSinceCreated <= 30);
    }

    private function isExpansionReady(Tenant $tenant): bool
    {
        $crmStatus = (string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW);
        $daysSinceCreated = $tenant->created_at?->diffInDays(now()) ?? 0;
        $hasOffer = (int) ($tenant->products_total ?? 0) > 0 || (int) ($tenant->services_total ?? 0) > 0;
        $hasDirectChannel = filled($tenant->whatsapp_number) || filled($tenant->phone) || filled($tenant->website_url);

        return in_array($crmStatus, [Tenant::CRM_STATUS_ACTIVE, Tenant::CRM_STATUS_UPSELL], true)
            && $this->planReader->isBasePlan($tenant)
            && $hasOffer
            && $hasDirectChannel
            && $daysSinceCreated >= 7;
    }

    private function isRenewalMotionCandidate(Tenant $tenant): bool
    {
        $crmStatus = (string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW);

        return $crmStatus === Tenant::CRM_STATUS_RENEWAL
            || $this->planReader->isRenewalWindow($tenant);
    }
}
