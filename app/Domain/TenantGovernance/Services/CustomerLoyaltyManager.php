<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerLoyaltyLedger;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use RuntimeException;

class CustomerLoyaltyManager
{
    public function syncProfile(CustomerProfile|int|string $profile): ?CustomerProfile
    {
        $profileModel = $profile instanceof CustomerProfile
            ? $profile->withoutRelations()
            : CustomerProfile::query()->withoutGlobalScopes()->find($profile);

        if (! $profileModel instanceof CustomerProfile) {
            return null;
        }

        $tenantId = (string) $profileModel->tenant_id;
        $profileId = (int) $profileModel->getKey();
        $earnRatio = $this->earnRatioForTenant($tenantId);

        /** @var Collection<int, Order> $orders */
        $orders = Order::query()
            ->withoutGlobalScopes()
            ->without(['items'])
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->whereNotIn('status', [Order::STATUS_CANCELLED])
            ->get(['id', 'order_number', 'total', 'created_at']);

        $validOrderIds = [];

        foreach ($orders as $order) {
            $points = max(0, (int) floor(((float) $order->total) / max(1, $earnRatio)));
            $validOrderIds[] = (int) $order->getKey();

            CustomerLoyaltyLedger::query()
                ->withoutGlobalScopes()
                ->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'customer_profile_id' => $profileId,
                        'order_id' => (int) $order->getKey(),
                        'type' => CustomerLoyaltyLedger::TYPE_EARN,
                    ],
                    [
                        'loyalty_reward_id' => null,
                        'created_by_user_id' => null,
                        'points_delta' => $points,
                        'monetary_value' => round((float) $order->total, 2),
                        'description' => 'Puntos por pedido '.((string) ($order->order_number ?: '#'.$order->getKey())),
                        'metadata' => [
                            'source' => 'order',
                            'earn_ratio' => $earnRatio,
                        ],
                        'awarded_at' => $order->created_at,
                    ],
                );
        }

        CustomerLoyaltyLedger::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->where('type', CustomerLoyaltyLedger::TYPE_EARN)
            ->when(
                $validOrderIds !== [],
                fn ($query) => $query->whereNotIn('order_id', $validOrderIds),
                fn ($query) => $query->whereNotNull('order_id'),
            )
            ->delete();

        $this->refreshProfileSummary($profileModel);

        return $profileModel->fresh();
    }

    public function awardBonus(CustomerProfile|int|string $profile, int $points, string $description, ?string $actorUserId = null): CustomerLoyaltyLedger
    {
        $profileModel = $this->resolveProfile($profile);

        if (! $profileModel instanceof CustomerProfile) {
            throw new RuntimeException('No se encontro el cliente para bonificar.');
        }

        if ($points <= 0) {
            throw new RuntimeException('La bonificacion debe ser mayor que cero.');
        }

        $entry = CustomerLoyaltyLedger::query()
            ->withoutGlobalScopes()
            ->create([
                'tenant_id' => (string) $profileModel->tenant_id,
                'customer_profile_id' => (int) $profileModel->getKey(),
                'created_by_user_id' => $actorUserId,
                'type' => CustomerLoyaltyLedger::TYPE_BONUS,
                'points_delta' => $points,
                'monetary_value' => 0,
                'description' => $description !== '' ? $description : 'Bonificacion manual',
                'metadata' => [
                    'source' => 'manual_bonus',
                ],
                'awarded_at' => now(),
            ]);

        $this->logProfileNote($profileModel, 'Se agregaron '.$points.' puntos al cliente. '.trim($description), $actorUserId);
        $this->refreshProfileSummary($profileModel);

        return $entry->fresh();
    }

    public function redeemReward(CustomerProfile|int|string $profile, LoyaltyReward|int $reward, ?string $actorUserId = null): CustomerLoyaltyLedger
    {
        $profileModel = $this->resolveProfile($profile);
        $rewardModel = $reward instanceof LoyaltyReward
            ? $reward->withoutRelations()
            : LoyaltyReward::query()->withoutGlobalScopes()->find($reward);

        if (! $profileModel instanceof CustomerProfile || ! $rewardModel instanceof LoyaltyReward) {
            throw new RuntimeException('No se pudo resolver el cliente o la recompensa.');
        }

        if ((string) $profileModel->tenant_id !== (string) $rewardModel->tenant_id) {
            throw new RuntimeException('La recompensa no pertenece al mismo negocio.');
        }

        if (! $rewardModel->isAvailable()) {
            throw new RuntimeException('La recompensa ya no esta disponible.');
        }

        $this->refreshProfileSummary($profileModel);
        $profileModel->refresh();

        if ((int) $profileModel->loyalty_points_balance < (int) $rewardModel->points_cost) {
            throw new RuntimeException('El cliente no tiene puntos suficientes para este canje.');
        }

        $entry = CustomerLoyaltyLedger::query()
            ->withoutGlobalScopes()
            ->create([
                'tenant_id' => (string) $profileModel->tenant_id,
                'customer_profile_id' => (int) $profileModel->getKey(),
                'loyalty_reward_id' => (int) $rewardModel->getKey(),
                'created_by_user_id' => $actorUserId,
                'type' => CustomerLoyaltyLedger::TYPE_REDEEM,
                'points_delta' => -((int) $rewardModel->points_cost),
                'monetary_value' => 0,
                'description' => 'Canje de recompensa: '.$rewardModel->name,
                'metadata' => [
                    'reward_name' => $rewardModel->name,
                    'reward_cost' => (int) $rewardModel->points_cost,
                ],
                'awarded_at' => now(),
            ]);

        $rewardModel->decrementStock();
        $this->logProfileNote($profileModel, 'Se canjeo la recompensa "'.$rewardModel->name.'".', $actorUserId);
        $this->refreshProfileSummary($profileModel);

        return $entry->fresh();
    }

    /**
     * @return array<int, string>
     */
    public function rewardOptionsForProfile(CustomerProfile|int|string $profile): array
    {
        $profileModel = $this->resolveProfile($profile);

        if (! $profileModel instanceof CustomerProfile) {
            return [];
        }

        $this->refreshProfileSummary($profileModel);
        $balance = (int) $profileModel->fresh()->loyalty_points_balance;

        return LoyaltyReward::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $profileModel->tenant_id)
            ->available()
            ->orderBy('points_cost')
            ->get()
            ->filter(fn (LoyaltyReward $reward): bool => (int) $reward->points_cost <= $balance)
            ->mapWithKeys(fn (LoyaltyReward $reward): array => [
                (int) $reward->getKey() => $reward->name.' - '.((int) $reward->points_cost).' pts',
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function rewardCampaignOptionsForTenant(Tenant|string $tenant): array
    {
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->getKey() : (string) $tenant;

        return LoyaltyReward::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->available()
            ->orderBy('points_cost')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (LoyaltyReward $reward): array => [
                (int) $reward->getKey() => $reward->name.' - '.((int) $reward->points_cost).' pts',
            ])
            ->all();
    }

    /**
     * @return array{headline: string, copy: string, cta_label: string}
     */
    public function rewardCalloutForProfile(CustomerProfile $profile, LoyaltyReward $reward): array
    {
        $balance = (int) ($profile->loyalty_points_balance ?? 0);
        $missing = max(0, (int) $reward->points_cost - $balance);

        if ($missing === 0) {
            return [
                'headline' => 'Reward listo: '.$reward->name.'.',
                'copy' => 'Ya puedes canjearlo por '.((int) $reward->points_cost).' puntos.',
                'cta_label' => 'Canjear reward',
            ];
        }

        return [
            'headline' => 'Reward sugerido: '.$reward->name.'.',
            'copy' => 'Te faltan '.$missing.' puntos para desbloquearlo.',
            'cta_label' => 'Ver progreso',
        ];
    }

    public function hasRedeemableReward(CustomerProfile|int|string $profile): bool
    {
        return $this->rewardOptionsForProfile($profile) !== [];
    }

    private function refreshProfileSummary(CustomerProfile $profile): void
    {
        $profile->refresh();
        $tenantId = (string) $profile->tenant_id;
        $profileId = (int) $profile->getKey();

        $ledgerBase = CustomerLoyaltyLedger::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId);

        $balance = (int) ((clone $ledgerBase)->sum('points_delta') ?: 0);
        $lifetimePoints = (int) ((clone $ledgerBase)->where('points_delta', '>', 0)->sum('points_delta') ?: 0);
        $lastActivityAt = (clone $ledgerBase)->max('awarded_at');

        $rewards = LoyaltyReward::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->available()
            ->orderBy('points_cost')
            ->get();

        $affordableRewards = $rewards->filter(fn (LoyaltyReward $reward): bool => (int) $reward->points_cost <= $balance)->values();
        $nextLockedReward = $rewards->first(fn (LoyaltyReward $reward): bool => (int) $reward->points_cost > $balance);
        $bestAvailableReward = $affordableRewards->last();

        $profile->forceFill([
            'loyalty_points_balance' => $balance,
            'lifetime_points_earned' => $lifetimePoints,
            'redeemable_rewards_count' => $affordableRewards->count(),
            'next_reward_name' => $bestAvailableReward?->name ?? $nextLockedReward?->name,
            'points_to_next_reward' => $nextLockedReward instanceof LoyaltyReward
                ? max(0, (int) $nextLockedReward->points_cost - $balance)
                : 0,
            'last_loyalty_activity_at' => $lastActivityAt,
        ])->save();
    }

    private function earnRatioForTenant(string $tenantId): int
    {
        $program = LoyaltyProgram::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        return max(1, (int) ($program?->earn_ratio ?: config('customer-loyalty.default_earn_ratio', 10)));
    }

    private function resolveProfile(CustomerProfile|int|string $profile): ?CustomerProfile
    {
        return $profile instanceof CustomerProfile
            ? $profile->withoutRelations()
            : CustomerProfile::query()->withoutGlobalScopes()->find($profile);
    }

    private function logProfileNote(CustomerProfile $profile, string $body, ?string $actorUserId = null): void
    {
        CustomerNote::query()
            ->withoutGlobalScopes()
            ->create([
                'tenant_id' => (string) $profile->tenant_id,
                'customer_profile_id' => (int) $profile->getKey(),
                'created_by_user_id' => $actorUserId,
                'assigned_user_id' => $profile->assigned_user_id,
                'location_id' => $profile->location_id,
                'note_type' => 'sale',
                'status' => 'resolved',
                'priority' => 'normal',
                'source_channel' => 'system',
                'requires_follow_up' => false,
                'body' => trim($body),
                'handled_at' => now(),
                'metadata' => [
                    'source' => 'loyalty',
                ],
            ]);
    }
}
