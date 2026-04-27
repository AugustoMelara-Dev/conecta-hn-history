<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\OperationalPlaybookTask;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class TenantCrmPortfolioManager
{
    public function __construct(
        private readonly BusinessSegmentResolver $segmentResolver,
        private readonly OperationalPlaybookTaskManager $playbookTaskManager,
    ) {}

    public function syncSignals(): void
    {
        $openTasksByTenant = OperationalPlaybookTask::query()
            ->withoutGlobalScopes()
            ->whereIn('status', [
                OperationalPlaybookTask::STATUS_OPEN,
                OperationalPlaybookTask::STATUS_IN_PROGRESS,
            ])
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('follow_up_status')
                    ->orWhere('follow_up_status', '!=', OperationalPlaybookTask::FOLLOW_UP_CLOSED);
            })
            ->get()
            ->groupBy(fn (OperationalPlaybookTask $task): string => (string) $task->tenant_id);

        Tenant::query()
            ->with('category:id,slug,name')
            ->withCount([
                'products as products_total' => fn (Builder $query): Builder => $query->withoutGlobalScopes(),
                'services as services_total' => fn (Builder $query): Builder => $query->withoutGlobalScopes(),
            ])
            ->get()
            ->each(function (Tenant $tenant) use ($openTasksByTenant): void {
                $tenantTasks = $openTasksByTenant->get((string) $tenant->getKey(), collect());
                $segmentKey = $this->segmentResolver->resolveForTenant($tenant);
                $signal = $this->evaluateSignal($tenant, $tenantTasks);
                $revenueMotion = $this->evaluateRevenueMotion($tenant, $segmentKey, $signal['trigger']);

                $this->persistSignal($tenant, $segmentKey, $signal, $revenueMotion);
                $this->syncAutomation($tenant, $segmentKey, $signal, $revenueMotion);
            });
    }

    /**
     * @param  Collection<int, OperationalPlaybookTask>  $tenantTasks
     * @return array{
     *     score: int,
     *     tone: string,
     *     reason: string|null,
     *     metric: string|null,
     *     source: string|null,
     *     flagged_at: \Illuminate\Support\Carbon|null,
     *     trigger: string|null,
     *     auto_next_touch: \Illuminate\Support\Carbon|null
     * }
     */
    private function evaluateSignal(Tenant $tenant, Collection $tenantTasks): array
    {
        $productsTotal = (int) ($tenant->products_total ?? 0);
        $servicesTotal = (int) ($tenant->services_total ?? 0);
        $daysUntilExpiry = $tenant->days_until_expiry;
        $billingOverdueCount = $tenantTasks
            ->filter(fn (OperationalPlaybookTask $task): bool => (string) $task->queue === 'billing' && $task->isOverdue())
            ->count();
        $overdueTaskCount = $tenantTasks
            ->filter(fn (OperationalPlaybookTask $task): bool => $task->isOverdue())
            ->count();
        $score = 0;
        $tone = 'info';
        $reason = null;
        $metric = null;
        $trigger = null;

        if (! (bool) $tenant->getAttribute('is_active')) {
            $score = 100;
            $tone = 'danger';
            $reason = 'Cuenta suspendida o congelada desde soporte. Necesita rescue plan antes de perder el negocio.';
            $metric = 'Kill switch activo';
            $trigger = 'tenant_churn_watch';
        } elseif ($billingOverdueCount > 0) {
            $score = 96;
            $tone = 'danger';
            $reason = 'La cartera ya tiene renovacion con SLA vencido y puede caer por cobro tardio.';
            $metric = $billingOverdueCount.' renovaciones vencidas';
            $trigger = 'renewal_attention';
        } elseif ($this->isPaidPlan($tenant) && $daysUntilExpiry !== null && $daysUntilExpiry <= 3) {
            $score = 92;
            $tone = 'danger';
            $reason = 'El plan PRO esta a punto de vencer y conviene cerrar continuidad antes del corte.';
            $metric = 'Vence en '.$daysUntilExpiry.' dias';
            $trigger = 'renewal_attention';
        } elseif ($tenant->crm_next_follow_up_at instanceof Carbon && $tenant->crm_next_follow_up_at->isPast()) {
            $score = 86;
            $tone = 'warning';
            $reason = 'La cuenta tiene siguiente toque atrasado y el seguimiento comercial ya rompio su SLA.';
            $metric = 'Toque atrasado '.$tenant->crm_next_follow_up_at->diffForHumans();
            $trigger = 'tenant_churn_watch';
        } elseif ((string) ($tenant->getAttributes()['crm_status'] ?? Tenant::CRM_STATUS_NEW) === Tenant::CRM_STATUS_AT_RISK) {
            $score = 82;
            $tone = 'warning';
            $reason = 'La cuenta ya esta marcada en riesgo dentro del CRM y pide ejecucion sostenida.';
            $metric = 'Estado CRM en riesgo';
            $trigger = 'tenant_churn_watch';
        } elseif ($tenant->updated_at instanceof Carbon && $tenant->updated_at->lt(now()->subDays(30))) {
            $score = 74;
            $tone = 'warning';
            $reason = 'No muestra movimiento reciente y podria enfriarse si nadie toma ownership comercial.';
            $metric = 'Sin actividad en 30+ dias';
        } elseif ($productsTotal === 0 && $servicesTotal === 0) {
            $score = 68;
            $tone = 'warning';
            $reason = 'Todavia no publico oferta visible, lo que golpea activacion, conversion y percepcion de valor.';
            $metric = 'Sin catalogo ni servicios';
        } elseif (blank($tenant->crm_owner_id) && $tenantTasks->count() >= 2) {
            $score = 62;
            $tone = 'info';
            $reason = 'La cuenta acumula playbooks activos sin owner persistente de cartera.';
            $metric = $tenantTasks->count().' playbooks sin cartera clara';
        } elseif ($overdueTaskCount > 0) {
            $score = 58;
            $tone = 'info';
            $reason = 'Tiene ejecucion abierta con frentes vencidos que merecen orden comercial.';
            $metric = $overdueTaskCount.' playbooks con SLA vencido';
        }

        return [
            'score' => $score,
            'tone' => $tone,
            'reason' => $reason,
            'metric' => $metric,
            'source' => $score > 0 ? 'automation' : null,
            'flagged_at' => $score > 0
                ? ($tenant->crm_watch_flagged_at !== null ? Carbon::instance($tenant->crm_watch_flagged_at) : now())
                : null,
            'trigger' => $trigger,
            'auto_next_touch' => $score >= 80 && ! ($tenant->crm_next_follow_up_at instanceof Carbon)
                ? now()->addHours(max(4, (int) ($tenant->crm_sla_hours ?? Tenant::DEFAULT_CRM_SLA_HOURS)))
                : null,
        ];
    }

    /**
     * @return array{
     *     trigger: string|null,
     *     metric: string|null,
     *     reason: string|null,
     *     motion: string|null,
     *     auto_next_touch: \Illuminate\Support\Carbon|null
     * }
     */
    private function evaluateRevenueMotion(Tenant $tenant, string $segmentKey, ?string $riskTrigger): array
    {
        $crmStatus = (string) ($tenant->getAttributes()['crm_status'] ?? Tenant::CRM_STATUS_NEW);
        $productsTotal = (int) ($tenant->products_total ?? 0);
        $servicesTotal = (int) ($tenant->services_total ?? 0);
        $hasOffer = $productsTotal > 0 || $servicesTotal > 0;
        $hasDirectChannel = filled($tenant->whatsapp_number) || filled($tenant->phone) || filled($tenant->website_url);
        $daysSinceCreated = $tenant->created_at instanceof Carbon ? (int) $tenant->created_at->diffInDays(now()) : 999;
        $segmentLabel = $this->segmentLabel($segmentKey);

        if (
            $riskTrigger !== null
            || ! (bool) $tenant->getAttribute('is_active')
            || in_array($crmStatus, [Tenant::CRM_STATUS_AT_RISK, Tenant::CRM_STATUS_PAUSED, Tenant::CRM_STATUS_RENEWAL], true)
        ) {
            return [
                'trigger' => null,
                'metric' => null,
                'reason' => null,
                'motion' => null,
                'auto_next_touch' => null,
            ];
        }

        if (in_array($crmStatus, [Tenant::CRM_STATUS_NEW, Tenant::CRM_STATUS_ONBOARDING], true) || (! $hasOffer && $daysSinceCreated <= 30)) {
            $metric = ! $hasOffer
                ? 'Sin oferta visible'
                : (! $hasDirectChannel ? 'Sin canal de cierre directo' : 'Onboarding sin activar del todo');

            return [
                'trigger' => 'activation_follow_up',
                'metric' => $metric,
                'reason' => "La cuenta todavia no termina de activarse en {$segmentLabel} y necesita empuje comercial temprano.",
                'motion' => 'activation',
                'auto_next_touch' => ! ($tenant->crm_next_follow_up_at instanceof Carbon)
                    ? now()->addHours(max(8, (int) ($tenant->crm_sla_hours ?? Tenant::DEFAULT_CRM_SLA_HOURS)))
                    : null,
            ];
        }

        if (
            in_array($crmStatus, [Tenant::CRM_STATUS_ACTIVE, Tenant::CRM_STATUS_UPSELL], true)
            && ! $this->isPaidPlan($tenant)
            && $hasOffer
            && $hasDirectChannel
            && $daysSinceCreated >= 7
        ) {
            return [
                'trigger' => 'expansion_opportunity',
                'metric' => "Base lista para crecer en {$segmentLabel}",
                'reason' => 'La cuenta ya tiene oferta y canal de cierre; conviene mover upgrade, plan PRO o expansion comercial.',
                'motion' => 'growth',
                'auto_next_touch' => ! ($tenant->crm_next_follow_up_at instanceof Carbon)
                    ? now()->addHours(max(12, (int) ($tenant->crm_sla_hours ?? Tenant::DEFAULT_CRM_SLA_HOURS)))
                    : null,
            ];
        }

        return [
            'trigger' => null,
            'metric' => null,
            'reason' => null,
            'motion' => null,
            'auto_next_touch' => null,
        ];
    }

    /**
     * @param  array{
     *     score: int,
     *     tone: string,
     *     reason: string|null,
     *     metric: string|null,
     *     source: string|null,
     *     flagged_at: \Illuminate\Support\Carbon|null,
     *     trigger: string|null,
     *     auto_next_touch: \Illuminate\Support\Carbon|null
     * }  $signal
     * @param  array{
     *     trigger: string|null,
     *     metric: string|null,
     *     reason: string|null,
     *     motion: string|null,
     *     auto_next_touch: \Illuminate\Support\Carbon|null
     * }  $revenueMotion
     */
    private function persistSignal(Tenant $tenant, string $segmentKey, array $signal, array $revenueMotion): void
    {
        $routing = $this->resolveCrmRouting($signal, $revenueMotion);

        if ((int) $signal['score'] === 0) {
            if (
                (int) ($tenant->crm_watch_score ?? 0) === 0
                && blank($tenant->crm_watch_reason)
                && blank($tenant->crm_watch_metric)
                && (string) ($tenant->getAttributes()['business_segment_key'] ?? '') === $segmentKey
                && ($routing['team'] === null || (string) ($tenant->getRawOriginal('crm_team') ?? '') === $routing['team'])
                && ($routing['priority'] === null || (string) ($tenant->getRawOriginal('crm_priority') ?? '') === $routing['priority'])
            ) {
                return;
            }

            $payload = [
                'business_segment_key' => $segmentKey,
                'crm_watch_score' => 0,
                'crm_watch_tone' => 'info',
                'crm_watch_source' => null,
                'crm_watch_metric' => null,
                'crm_watch_reason' => null,
                'crm_watch_flagged_at' => null,
            ];

            if ($routing['team'] !== null) {
                $payload['crm_team'] = $routing['team'];
            }

            if ($routing['priority'] !== null) {
                $payload['crm_priority'] = $routing['priority'];
            }

            $tenant->forceFill($payload)->save();

            return;
        }

        $payload = [
            'business_segment_key' => $segmentKey,
            'crm_watch_score' => (int) $signal['score'],
            'crm_watch_tone' => (string) $signal['tone'],
            'crm_watch_source' => $signal['source'],
            'crm_watch_metric' => $signal['metric'],
            'crm_watch_reason' => $signal['reason'],
            'crm_watch_flagged_at' => $signal['flagged_at'],
        ];

        if ($routing['team'] !== null) {
            $payload['crm_team'] = $routing['team'];
        }

        if ($routing['priority'] !== null) {
            $payload['crm_priority'] = $routing['priority'];
        }

        $portfolioTouch = $signal['auto_next_touch'] instanceof Carbon
            ? $signal['auto_next_touch']
            : ($revenueMotion['auto_next_touch'] instanceof Carbon ? $revenueMotion['auto_next_touch'] : null);

        if ($portfolioTouch instanceof Carbon && ! ($tenant->crm_next_follow_up_at instanceof Carbon)) {
            $payload['crm_next_follow_up_at'] = $portfolioTouch;
        }

        $tenant->forceFill($payload)->save();
    }

    /**
     * @param  array{
     *     score: int,
     *     tone: string,
     *     reason: string|null,
     *     metric: string|null,
     *     source: string|null,
     *     flagged_at: \Illuminate\Support\Carbon|null,
     *     trigger: string|null,
     *     auto_next_touch: \Illuminate\Support\Carbon|null
     * }  $signal
     * @param  array{
     *     trigger: string|null,
     *     metric: string|null,
     *     reason: string|null,
     *     motion: string|null,
     *     auto_next_touch: \Illuminate\Support\Carbon|null
     * }  $revenueMotion
     */
    private function syncAutomation(Tenant $tenant, string $segmentKey, array $signal, array $revenueMotion): void
    {
        $riskTrigger = is_string($signal['trigger']) && $signal['trigger'] !== '' ? $signal['trigger'] : null;
        $revenueTrigger = is_string($revenueMotion['trigger']) && $revenueMotion['trigger'] !== '' ? $revenueMotion['trigger'] : null;
        $activeTriggers = array_values(array_filter([$riskTrigger, $riskTrigger === null ? $revenueTrigger : null]));
        $managedTriggers = ['renewal_attention', 'tenant_churn_watch', 'activation_follow_up', 'expansion_opportunity'];

        foreach ($managedTriggers as $triggerKey) {
            if (in_array($triggerKey, $activeTriggers, true)) {
                continue;
            }

            $this->closeManagedTask($tenant, $triggerKey);
        }

        foreach ($activeTriggers as $triggerKey) {
            $this->playbookTaskManager->syncForTrigger($tenant, $triggerKey, [
                'days_until_expiry' => $tenant->days_until_expiry,
                'watch_metric' => $signal['metric'],
                'watch_reason' => $signal['reason'],
                'activation_metric' => $revenueMotion['motion'] === 'activation' ? $revenueMotion['metric'] : null,
                'activation_reason' => $revenueMotion['motion'] === 'activation' ? $revenueMotion['reason'] : null,
                'expansion_metric' => $revenueMotion['motion'] === 'growth' ? $revenueMotion['metric'] : null,
                'expansion_reason' => $revenueMotion['motion'] === 'growth' ? $revenueMotion['reason'] : null,
                'segment_label' => $this->segmentLabel($segmentKey),
            ]);
        }
    }

    private function closeManagedTask(Tenant $tenant, string $triggerKey): void
    {
        $task = OperationalPlaybookTask::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('trigger_key', $triggerKey)
            ->whereIn('status', [
                OperationalPlaybookTask::STATUS_OPEN,
                OperationalPlaybookTask::STATUS_IN_PROGRESS,
            ])
            ->latest('created_at')
            ->first();

        if (! $task instanceof OperationalPlaybookTask) {
            return;
        }

        $task->forceFill([
            'status' => OperationalPlaybookTask::STATUS_COMPLETED,
            'follow_up_status' => OperationalPlaybookTask::FOLLOW_UP_CLOSED,
            'next_follow_up_at' => null,
            'completed_at' => now(),
            'last_followed_up_at' => now(),
            'internal_notes' => $this->appendInternalNote(
                (string) ($task->internal_notes ?? ''),
                'Cierre automatico: la senal CRM ya no exige watchlist activa.',
            ),
        ])->save();
    }

    /**
     * @param  array{
     *     score: int,
     *     tone: string,
     *     reason: string|null,
     *     metric: string|null,
     *     source: string|null,
     *     flagged_at: \Illuminate\Support\Carbon|null,
     *     trigger: string|null,
     *     auto_next_touch: \Illuminate\Support\Carbon|null
     * }  $signal
     * @param  array{
     *     trigger: string|null,
     *     metric: string|null,
     *     reason: string|null,
     *     motion: string|null,
     *     auto_next_touch: \Illuminate\Support\Carbon|null
     * }  $revenueMotion
     * @return array{team: string|null, priority: string|null}
     */
    private function resolveCrmRouting(array $signal, array $revenueMotion): array
    {
        return match (true) {
            $signal['trigger'] === 'renewal_attention' => [
                'team' => Tenant::CRM_TEAM_BILLING,
                'priority' => Tenant::CRM_PRIORITY_CRITICAL,
            ],
            $signal['trigger'] === 'tenant_churn_watch' => [
                'team' => Tenant::CRM_TEAM_RETENTION,
                'priority' => ((int) $signal['score']) >= 90 ? Tenant::CRM_PRIORITY_CRITICAL : Tenant::CRM_PRIORITY_HIGH,
            ],
            $revenueMotion['motion'] === 'activation' => [
                'team' => Tenant::CRM_TEAM_ACTIVATION,
                'priority' => Tenant::CRM_PRIORITY_HIGH,
            ],
            $revenueMotion['motion'] === 'growth' => [
                'team' => Tenant::CRM_TEAM_GROWTH,
                'priority' => Tenant::CRM_PRIORITY_HIGH,
            ],
            default => [
                'team' => null,
                'priority' => null,
            ],
        };
    }

    private function appendInternalNote(string $existing, string $incoming): string
    {
        $incoming = trim($incoming);

        if ($incoming === '') {
            return $existing;
        }

        $entry = now()->format('d/m/Y H:i').' - Sistema: '.$incoming;
        $existing = trim($existing);

        return $existing === '' ? $entry : $entry."\n\n".$existing;
    }

    private function segmentLabel(string $segmentKey): string
    {
        return app(\App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface::class)
            ->labelForRubroKey($segmentKey);
    }

    private function isPaidPlan(Tenant $tenant): bool
    {
        $planKey = $tenant->getRawOriginal('plan_type') ?: ($tenant->getAttributes()['plan_type'] ?? null);

        return match (true) {
            $planKey instanceof TenantPlan => $planKey !== TenantPlan::SEMILLA,
            is_string($planKey) => $planKey !== '' && $planKey !== Tenant::PLAN_SEMILLA,
            default => false,
        };
    }
}
