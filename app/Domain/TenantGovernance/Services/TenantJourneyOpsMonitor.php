<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerConversation;
use App\Models\CustomerJourneyRun;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class TenantJourneyOpsMonitor
{
    public function __construct(
        private readonly OperationalPlaybookTaskManager $playbookTaskManager,
        private readonly BusinessSegmentResolver $segmentResolver,
    ) {}

    /**
     * @return array{conversion:int,no_show:int,reward:int}
     */
    public function syncSignals(int $limit = 50): array
    {
        $created = [
            'conversion' => 0,
            'no_show' => 0,
            'reward' => 0,
        ];

        $this->tenantCandidates($limit)->each(function (Tenant $tenant) use (&$created): void {
            $signals = $this->analyzeTenant($tenant);

            if (($signals['conversion_watch'] ?? false) === true) {
                $task = $this->playbookTaskManager->syncForTrigger($tenant, 'journey_conversion_watch', [
                    'segment_label' => $this->segmentLabelForTenant($tenant),
                    'watch_metric' => (string) $signals['conversion_metric'],
                    'watch_reason' => (string) $signals['conversion_reason'],
                ]);

                if ($task !== null) {
                    $created['conversion']++;
                }
            }

            if (($signals['no_show_watch'] ?? false) === true) {
                $task = $this->playbookTaskManager->syncForTrigger($tenant, 'journey_no_show_watch', [
                    'segment_label' => $this->segmentLabelForTenant($tenant),
                    'watch_metric' => (string) $signals['no_show_metric'],
                    'watch_reason' => (string) $signals['no_show_reason'],
                ]);

                if ($task !== null) {
                    $created['no_show']++;
                }
            }

            if (($signals['reward_watch'] ?? false) === true) {
                $task = $this->playbookTaskManager->syncForTrigger($tenant, 'journey_reward_watch', [
                    'segment_label' => $this->segmentLabelForTenant($tenant),
                    'watch_metric' => (string) $signals['reward_metric'],
                    'watch_reason' => (string) $signals['reward_reason'],
                ]);

                if ($task !== null) {
                    $created['reward']++;
                }
            }
        });

        return $created;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenantCandidates(int $limit): Collection
    {
        return Tenant::query()
            ->withoutGlobalScopes()
            ->where(function ($query): void {
                $query->whereNotNull('business_segment_key')
                    ->orWhereNotNull('user_id');
            })
            ->latest('updated_at')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * @return array<string, bool|string>
     */
    private function analyzeTenant(Tenant $tenant): array
    {
        $window = now()->subDays(30)->startOfDay();

        $conversations = CustomerConversation::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where(function ($query) use ($window): void {
                $query->where('updated_at', '>=', $window)
                    ->orWhere('closed_at', '>=', $window)
                    ->orWhere('last_customer_message_at', '>=', $window);
            })
            ->get(['id', 'metadata', 'status', 'closed_at']);

        $journeys = CustomerJourneyRun::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('updated_at', '>=', $window)
            ->get(['id', 'sequence_state', 'due_state', 'status', 'metadata']);

        $closedCount = $conversations->where('status', 'closed')->count();
        $activeJourneys = $journeys->where('status', 'active')->count();
        $stalledJourneys = $journeys->where('sequence_state', 'stalled')->count();
        $breachedJourneys = $journeys->where('due_state', 'breached')->count();
        $conversionRate = $activeJourneys > 0 ? round(($closedCount / max(1, $activeJourneys)) * 100, 1) : 0.0;

        $signals = $conversations
            ->map(fn (CustomerConversation $conversation): array => (array) data_get($conversation->metadata, 'business_signals', []));

        $noShows = $signals->filter(fn (array $signalSet): bool => array_key_exists('appointment_no_show', $signalSet))->count();
        $recoveredNoShows = $signals->filter(fn (array $signalSet): bool => array_key_exists('no_show_recovered', $signalSet))->count();
        $rewardRedemptions = $signals->filter(fn (array $signalSet): bool => array_key_exists('reward_redemption', $signalSet))->count();
        $rewardRepurchases = $signals->filter(fn (array $signalSet): bool => array_key_exists('reward_repurchase', $signalSet))->count();

        return [
            'conversion_watch' => $activeJourneys >= 4 && ($stalledJourneys >= 2 || $breachedJourneys >= 2 || $conversionRate < 35),
            'conversion_metric' => "{$stalledJourneys} journeys trabados / {$conversionRate}% de cierre",
            'conversion_reason' => 'La secuencia comercial de clientes viene perdiendo traccion y conviene intervenir con ownership humano antes de que baje mas el revenue asistido.',
            'no_show_watch' => $noShows >= 2 && $noShows > $recoveredNoShows,
            'no_show_metric' => "{$noShows} no-show vs {$recoveredNoShows} recuperados",
            'no_show_reason' => 'El negocio esta dejando mas ausencias sin rescatar de las que logra recuperar; conviene empujar reaccion operativa por sede y equipo.',
            'reward_watch' => $rewardRedemptions >= 2 && $rewardRepurchases < $rewardRedemptions,
            'reward_metric' => "{$rewardRedemptions} canjes / {$rewardRepurchases} recompras",
            'reward_reason' => 'Las recompensas ya se usan, pero no estan convirtiendo en recompra con la fuerza esperada; conviene intervenir journey, oferta y seguimiento humano.',
        ];
    }

    private function segmentLabelForTenant(Tenant $tenant): string
    {
        $segmentKey = $this->segmentResolver->resolveForTenant($tenant);
        $profile = $this->segmentResolver->internalProfile($segmentKey, $tenant->business_type?->value ?? 'retail');
        $label = $profile['label'] ?? null;

        return is_string($label) && $label !== '' ? $label : str($segmentKey)->replace('_', ' ')->title()->value();
    }
}
