<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\SuperAdmin;

use App\Models\OperationalPlaybookTask;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SuperAdminOperationsReadModel
{
    public function __construct(
        private readonly SuperAdminTenantPlanReader $planReader,
    ) {}

    /**
     * @param  Collection<int, OperationalPlaybookTask>  $tasks
     * @return list<array{task: string, business: string, owner: string, internal_owner: string, assignee: string, follow_up: string, next_touch: string, queue: string, sla: string, reason: string, tone: string, href: string}>
     */
    public function globalWorkboard(Collection $tasks, string $operationsUrl, string $tenantsUrl, callable $safeRoute): array
    {
        return $tasks
            ->take(6)
            ->map(fn (OperationalPlaybookTask $task): array => $this->mapWorkboardTask($task, $operationsUrl, $tenantsUrl, $safeRoute))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, OperationalPlaybookTask>  $tasks
     * @return list<array{name: string, context: string, task_count: int, overdue_count: int, next_touch_count: int, tone: string, href: string}>
     */
    public function assigneeLoad(Collection $tasks, string $operationsUrl): array
    {
        return $tasks
            ->groupBy(function (OperationalPlaybookTask $task): string {
                if (filled($task->admin_owner_id)) {
                    return 'user:'.(string) $task->admin_owner_id;
                }

                return 'unassigned';
            })
            ->map(function (Collection $groupedTasks) use ($operationsUrl): array {
                /** @var OperationalPlaybookTask $first */
                $first = $groupedTasks->first();
                $adminOwner = $first->adminOwner;
                $adminOwner = $adminOwner instanceof User ? $adminOwner : null;
                $ownerName = $adminOwner instanceof User ? $adminOwner->name : 'Sin owner interno';
                $queueSummary = $groupedTasks
                    ->map(fn (OperationalPlaybookTask $task): string => (string) $task->queue_label)
                    ->unique()
                    ->take(3)
                    ->implode(', ');
                $tenantCount = $groupedTasks
                    ->map(fn (OperationalPlaybookTask $task): string => (string) $task->tenant_id)
                    ->unique()
                    ->count();
                $overdueCount = $groupedTasks->filter(fn (OperationalPlaybookTask $task): bool => $task->isOverdue())->count();
                $taskCount = $groupedTasks->count();
                $nextTouchCount = $groupedTasks->filter(function (OperationalPlaybookTask $task): bool {
                    $nextFollowUpAt = $task->next_follow_up_at;

                    return $this->resolveCarbon($nextFollowUpAt)?->lte(now()->endOfDay()) ?? false;
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
                    'href' => $operationsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['overdue_count'] * 100) + $item['task_count'])
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @return list<array{name: string, context: string, account_count: int, overdue_count: int, watch_count: int, renewal_count: int, tone: string, href: string}>
     */
    public function ownerPortfolio(Collection $portfolio, string $operationsUrl): array
    {
        return $portfolio
            ->groupBy(function (Tenant $tenant): string {
                if (filled($tenant->crm_owner_id)) {
                    return 'owner:'.(string) $tenant->crm_owner_id;
                }

                return 'owner:none';
            })
            ->map(function (Collection $tenants) use ($operationsUrl): array {
                /** @var Tenant $first */
                $first = $tenants->first();
                $crmOwner = $first->crmOwner;
                $crmOwner = $crmOwner instanceof User ? $crmOwner : null;
                $overdueCount = $tenants->filter(fn (Tenant $tenant): bool => $tenant->crm_next_follow_up_at instanceof Carbon && $tenant->crm_next_follow_up_at->isPast())->count();
                $watchCount = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $renewalCount = $tenants->filter(fn (Tenant $tenant): bool => $this->isRenewalMotionCandidate($tenant))->count();
                $teamSummary = $tenants
                    ->map(fn (Tenant $tenant): string => (string) $tenant->crm_team_label)
                    ->unique()
                    ->take(3)
                    ->implode(', ');

                return [
                    'name' => $crmOwner instanceof User ? $crmOwner->name : 'Sin owner interno',
                    'context' => collect([
                        $teamSummary !== '' ? $teamSummary : null,
                        $tenants->count().' cuentas',
                    ])->filter()->implode(' / '),
                    'account_count' => $tenants->count(),
                    'overdue_count' => $overdueCount,
                    'watch_count' => $watchCount,
                    'renewal_count' => $renewalCount,
                    'tone' => $overdueCount > 0 ? 'danger' : ($watchCount > 0 ? 'warning' : ($crmOwner instanceof User ? 'info' : 'gray')),
                    'href' => $operationsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['watch_count'] * 100) + ($item['overdue_count'] * 50) + $item['account_count'])
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @return list<array{team: string, context: string, account_count: int, overdue_count: int, watch_count: int, next_touch_count: int, tone: string, href: string}>
     */
    public function teamPortfolio(Collection $portfolio, string $operationsUrl): array
    {
        return $portfolio
            ->groupBy(fn (Tenant $tenant): string => (string) ($tenant->getRawOriginal('crm_team') ?? Tenant::CRM_TEAM_ACTIVATION))
            ->map(function (Collection $tenants, string $team) use ($operationsUrl): array {
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
                    'href' => $operationsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['watch_count'] * 100) + ($item['overdue_count'] * 50) + $item['account_count'])
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @return list<array{business: string, owner: string, team: string, status: string, backlog: string, next_touch: string, watch: string, tone: string, href: string}>
     */
    public function slaBacklog(Collection $portfolio, callable $safeRoute): array
    {
        return $portfolio
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
            ->map(function (Tenant $tenant) use ($safeRoute): array {
                $nextTouch = $tenant->crm_next_follow_up_at;
                $crmOwner = $tenant->crmOwner;
                $crmOwner = $crmOwner instanceof User ? $crmOwner : null;

                return [
                    'business' => (string) $tenant->name,
                    'owner' => $crmOwner instanceof User ? $crmOwner->name : 'Sin owner interno',
                    'team' => (string) $tenant->crm_team_label,
                    'status' => (string) $tenant->crm_status_label,
                    'backlog' => $nextTouch instanceof Carbon && $nextTouch->isPast()
                        ? 'Atrasado '.$nextTouch->diffForHumans()
                        : 'Alerta alta sin toque cerrado',
                    'next_touch' => $nextTouch instanceof Carbon ? $nextTouch->format('d/m H:i') : 'Sin fecha agendada',
                    'watch' => $tenant->crm_watch_label,
                    'tone' => (string) ($tenant->crm_watch_tone ?? 'warning'),
                    'href' => $safeRoute('filament.admin.resources.tenants.edit', ['record' => $tenant]),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @return list<array{stage: string, count: int, share: string, copy: string, tone: string, href: string}>
     */
    public function commercialFunnel(Collection $portfolio, int $tenantsCount, string $tenantsUrl): array
    {
        $total = max(1, $tenantsCount);
        $counts = $portfolio->countBy(fn (Tenant $tenant): string => (string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW));

        $stages = [
            Tenant::CRM_STATUS_NEW => ['label' => 'Nuevo', 'copy' => 'Alta sin activacion comercial cerrada.', 'tone' => 'gray'],
            Tenant::CRM_STATUS_ONBOARDING => ['label' => 'Onboarding', 'copy' => 'Activando oferta, canales y equipo inicial.', 'tone' => 'info'],
            Tenant::CRM_STATUS_ACTIVE => ['label' => 'Operando', 'copy' => 'Cuenta en marcha y lista para profundizar valor.', 'tone' => 'success'],
            Tenant::CRM_STATUS_UPSELL => ['label' => 'Upsell', 'copy' => 'Cuenta lista para crecer ticket, modulos o plan.', 'tone' => 'warning'],
            Tenant::CRM_STATUS_RENEWAL => ['label' => 'Renovacion', 'copy' => 'Continuidad de plan en foco comercial.', 'tone' => 'danger'],
            Tenant::CRM_STATUS_AT_RISK => ['label' => 'En riesgo', 'copy' => 'Cuenta que exige recovery plan o soporte ejecutivo.', 'tone' => 'danger'],
            Tenant::CRM_STATUS_PAUSED => ['label' => 'Pausado', 'copy' => 'Cuenta frenada que necesita decision o rescate.', 'tone' => 'gray'],
        ];

        return collect($stages)
            ->map(function (array $stage, string $status) use ($counts, $total, $tenantsUrl): array {
                $count = (int) ($counts[$status] ?? 0);

                return [
                    'stage' => $stage['label'],
                    'count' => $count,
                    'share' => number_format(($count / $total) * 100, 1).'%',
                    'copy' => $stage['copy'],
                    'tone' => $stage['tone'],
                    'href' => $tenantsUrl,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, OperationalPlaybookTask>  $tasks
     * @param  Collection<int, SubscriptionPayment>  $monthlyProConversions
     * @return list<array{playbook: string, business: string, queue: string, segment: string, owner: string, follow_up: string, next_touch: string, sla: string, conversion_signal: string, tone: string, href: string}>
     */
    public function revenuePlaybooks(Collection $tasks, Collection $monthlyProConversions, string $operationsUrl, string $tenantsUrl, callable $safeRoute): array
    {
        return $tasks
            ->filter(fn (OperationalPlaybookTask $task): bool => in_array((string) $task->queue, ['activation', 'growth', 'billing'], true))
            ->sortByDesc(function (OperationalPlaybookTask $task) use ($monthlyProConversions): int {
                $conversionWeight = (string) $task->queue === 'growth'
                    ? $this->convertedTenantCount(
                        $monthlyProConversions,
                        fn (SubscriptionPayment $payment): bool => $this->segmentKeyForPayment($payment) === (string) ($task->segment_key ?? '')
                    ) * 100
                    : 0;

                return $conversionWeight + ($task->isOverdue() ? 1000 : 0);
            })
            ->take(8)
            ->map(function (OperationalPlaybookTask $task) use ($monthlyProConversions, $operationsUrl, $tenantsUrl, $safeRoute): array {
                $conversionSignal = $this->revenuePlaybookConversionSignal($task, $monthlyProConversions);

                return [
                    'playbook' => (string) $task->title,
                    'business' => (string) (($task->tenant instanceof Tenant ? $task->tenant->name : null) ?? 'Negocio sin nombre'),
                    'queue' => (string) $task->queue_label,
                    'segment' => $this->segmentLabel((string) ($task->segment_key ?? '')),
                    'owner' => $this->internalOwnerLabel($task),
                    'follow_up' => (string) $task->follow_up_status_label,
                    'next_touch' => $this->nextTouchLabel($task),
                    'sla' => $this->formatSla($task),
                    'conversion_signal' => $conversionSignal,
                    'tone' => (string) $this->revenuePlaybookTone($task, $conversionSignal),
                    'href' => $this->taskAdminHref($task, $operationsUrl, $tenantsUrl, $safeRoute),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @return list<array{business: string, owner: string, internal_owner: string, reason: string, metric: string, flagged_at: string, tone: string, href: string}>
     */
    public function churnWatchlist(Collection $portfolio, callable $safeRoute): array
    {
        return $portfolio
            ->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)
            ->sortByDesc(fn (Tenant $tenant): int => (int) ($tenant->crm_watch_score ?? 0))
            ->take(6)
            ->map(function (Tenant $tenant) use ($safeRoute): array {
                $tenantUser = $tenant->user;
                $tenantUser = $tenantUser instanceof User ? $tenantUser : null;
                $crmOwner = $tenant->crmOwner;
                $crmOwner = $crmOwner instanceof User ? $crmOwner : null;

                return [
                    'business' => (string) $tenant->name,
                    'owner' => $this->tenantOwnerLabel($tenantUser),
                    'internal_owner' => $crmOwner instanceof User ? $crmOwner->name : 'Sin owner interno',
                    'reason' => (string) ($tenant->crm_watch_reason ?? 'Cuenta en observacion.'),
                    'metric' => (string) ($tenant->crm_watch_metric ?? 'Watchlist activa'),
                    'flagged_at' => $tenant->crm_watch_flagged_at instanceof Carbon
                        ? 'Abierto '.$tenant->crm_watch_flagged_at->diffForHumans()
                        : 'Sin fecha',
                    'tone' => (string) ($tenant->crm_watch_tone ?? 'warning'),
                    'href' => $safeRoute('filament.admin.resources.tenants.edit', ['record' => $tenant]),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, OperationalPlaybookTask>  $tasks
     * @return list<array{business: string, playbook: string, queue: string, owner: string, assignee: string, follow_up: string, next_touch: string, sla: string, tone: string, href: string}>
     */
    public function commercialFollowups(Collection $tasks, string $operationsUrl, string $tenantsUrl, callable $safeRoute): array
    {
        return $tasks
            ->filter(fn (OperationalPlaybookTask $task): bool => in_array((string) $task->queue, ['leads', 'retention'], true))
            ->take(6)
            ->map(function (OperationalPlaybookTask $task) use ($operationsUrl, $tenantsUrl, $safeRoute): array {
                $tenant = $task->tenant;
                $tenant = $tenant instanceof Tenant ? $tenant : null;
                $assigneeModel = $task->assignee;
                $assigneeModel = $assigneeModel instanceof User ? $assigneeModel : null;
                $assignee = $assigneeModel?->name;

                if ($assignee === null || $assignee === '') {
                    $presetLabel = $this->presetLabel((string) ($task->suggested_preset_key ?? ''));
                    $assignee = $presetLabel !== '' ? 'Sin sugerencia fija / '.$presetLabel : 'Sin sugerencia fija';
                }

                return [
                    'business' => (string) ($tenant instanceof Tenant ? $tenant->name : 'Negocio sin nombre'),
                    'playbook' => (string) $task->title,
                    'queue' => (string) $task->queue_label,
                    'owner' => $this->internalOwnerLabel($task),
                    'assignee' => $assignee,
                    'follow_up' => (string) $task->follow_up_status_label,
                    'next_touch' => $this->nextTouchLabel($task),
                    'sla' => $this->formatSla($task),
                    'tone' => $this->playbookTone($task),
                    'href' => $this->taskAdminHref($task, $operationsUrl, $tenantsUrl, $safeRoute),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SubscriptionPayment>  $monthlyProConversions
     */
    private function convertedTenantCount(Collection $monthlyProConversions, callable $filter): int
    {
        return $monthlyProConversions
            ->filter($filter)
            ->map(fn (SubscriptionPayment $payment): string => (string) $payment->tenant_id)
            ->filter()
            ->unique()
            ->count();
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
        return app(\App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface::class)
            ->labelForRubroKey($segmentKey);
    }

    private function ownerKeyForPayment(SubscriptionPayment $payment): string
    {
        $tenant = $payment->tenant;

        return $tenant instanceof Tenant && filled($tenant->crm_owner_id)
            ? (string) $tenant->crm_owner_id
            : 'unassigned';
    }

    private function isRenewalMotionCandidate(Tenant $tenant): bool
    {
        $crmStatus = (string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW);

        return $crmStatus === Tenant::CRM_STATUS_RENEWAL
            || $this->planReader->isRenewalWindow($tenant);
    }

    private function revenuePlaybookConversionSignal(OperationalPlaybookTask $task, Collection $monthlyProConversions): string
    {
        if ((string) $task->queue !== 'growth') {
            return 'Playbook operativo para mover ingresos, continuidad o activacion.';
        }

        $segmentKey = (string) ($task->segment_key ?? '');
        $segmentLabel = $this->segmentLabel($segmentKey);
        $segmentConversions = $this->convertedTenantCount(
            $monthlyProConversions,
            fn (SubscriptionPayment $payment): bool => $this->segmentKeyForPayment($payment) === $segmentKey
        );
        $tenant = $task->tenant;
        $tenant = $tenant instanceof Tenant ? $tenant : null;
        $ownerKey = filled($task->admin_owner_id)
            ? (string) $task->admin_owner_id
            : ($tenant instanceof Tenant && filled($tenant->crm_owner_id) ? (string) $tenant->crm_owner_id : 'unassigned');
        $ownerConversions = $this->convertedTenantCount(
            $monthlyProConversions,
            fn (SubscriptionPayment $payment): bool => $this->ownerKeyForPayment($payment) === $ownerKey
        );

        if ($segmentConversions > 0 && $ownerConversions > 0) {
            return $segmentConversions.' PRO este mes en '.$segmentLabel.' / owner con '.$ownerConversions.' cierres.';
        }

        if ($segmentConversions > 0) {
            return $segmentConversions.' PRO este mes en '.$segmentLabel.'. Usa esa señal para priorizar el upsell.';
        }

        if ($ownerConversions > 0) {
            return 'Owner con '.$ownerConversions.' conversiones PRO este mes. Conviene acelerar este playbook.';
        }

        return 'Sin conversiones PRO este mes en este vertical. Valida propuesta antes de empujar expansión.';
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
        $dueAt = $this->resolveCarbon($task->due_at);

        if (! $dueAt instanceof Carbon) {
            return 'Sin SLA';
        }

        if ($task->isOverdue()) {
            return 'SLA vencido';
        }

        return 'Vence '.$dueAt->diffForHumans();
    }

    private function nextTouchLabel(OperationalPlaybookTask $task): string
    {
        $nextFollowUpAt = $this->resolveCarbon($task->next_follow_up_at);

        if (! $nextFollowUpAt instanceof Carbon) {
            return 'Sin siguiente toque';
        }

        if ($nextFollowUpAt->isPast()) {
            return 'Toque atrasado '.$nextFollowUpAt->diffForHumans();
        }

        return 'Siguiente '.$nextFollowUpAt->diffForHumans();
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

        $tenant = $task->tenant;
        $tenant = $tenant instanceof Tenant ? $tenant : null;
        $crmOwner = $tenant?->crmOwner;

        if ($crmOwner instanceof User) {
            return (string) ($crmOwner->name ?: $crmOwner->email).' / cartera';
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

    private function taskAdminHref(OperationalPlaybookTask $task, string $operationsUrl, string $tenantsUrl, callable $safeRoute): string
    {
        if ($operationsUrl !== '#') {
            return $operationsUrl.'?tableSearch='.urlencode((string) $task->title);
        }

        $tenant = $task->tenant;

        return $tenant instanceof Tenant
            ? $safeRoute('filament.admin.resources.tenants.edit', ['record' => $tenant])
            : $tenantsUrl;
    }

    /**
     * @return array{task: string, business: string, owner: string, internal_owner: string, assignee: string, follow_up: string, next_touch: string, queue: string, sla: string, reason: string, tone: string, href: string}
     */
    private function mapWorkboardTask(OperationalPlaybookTask $task, string $operationsUrl, string $tenantsUrl, callable $safeRoute): array
    {
        $tenant = $task->tenant;
        $tenant = $tenant instanceof Tenant ? $tenant : null;
        $assigneeModel = $task->assignee;
        $assigneeModel = $assigneeModel instanceof User ? $assigneeModel : null;
        $tenantUser = $tenant?->user;
        $tenantUser = $tenantUser instanceof User ? $tenantUser : null;
        $businessAssignee = $assigneeModel?->name;

        if ($businessAssignee === null || $businessAssignee === '') {
            $presetLabel = $this->presetLabel((string) ($task->suggested_preset_key ?? ''));
            $businessAssignee = $presetLabel !== '' ? 'Sugerido negocio: '.$presetLabel : 'Sin sugerencia de negocio';
        }

        return [
            'task' => (string) $task->title,
            'business' => (string) ($tenant instanceof Tenant ? $tenant->name : 'Negocio sin nombre'),
            'owner' => $this->tenantOwnerLabel($tenantUser),
            'internal_owner' => $this->internalOwnerLabel($task),
            'assignee' => $businessAssignee,
            'follow_up' => (string) $task->follow_up_status_label,
            'next_touch' => $this->nextTouchLabel($task),
            'queue' => (string) $task->queue_label,
            'sla' => $this->formatSla($task),
            'reason' => $this->taskReason($task),
            'tone' => $this->playbookTone($task),
            'href' => $this->taskAdminHref($task, $operationsUrl, $tenantsUrl, $safeRoute),
        ];
    }

    private function resolveCarbon(mixed $value): ?Carbon
    {
        return $value instanceof Carbon ? $value : null;
    }
}
