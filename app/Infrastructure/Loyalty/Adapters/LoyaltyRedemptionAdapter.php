<?php

declare(strict_types=1);

namespace App\Infrastructure\Loyalty\Adapters;

use App\Application\Loyalty\Contracts\LoyaltyRedemptionPortInterface;
use App\Models\LoyaltyLedger;
use App\Models\LoyaltyReward;
use Illuminate\Support\Facades\DB;

class LoyaltyRedemptionAdapter implements LoyaltyRedemptionPortInterface
{
    public function getRewardCostIfAvailable(int $rewardId, int|string $tenantId): ?int
    {
        /** @var LoyaltyReward|null $reward */
        $reward = LoyaltyReward::where('id', $rewardId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $reward || ! $reward->isAvailable()) {
            return null;
        }

        return $reward->points_cost;
    }

    public function getUserBalance(int $userId, int|string $tenantId): int
    {
        return (int) LoyaltyLedger::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->sum('amount');
    }

    public function processRedemption(int $userId, int|string $tenantId, int $rewardId, int $cost): void
    {
        DB::transaction(function () use ($userId, $tenantId, $rewardId, $cost) {
            /** @var LoyaltyReward|null $reward */
            $reward = LoyaltyReward::where('id', $rewardId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if ($reward) {
                $reward->decrementStock();
            }

            LoyaltyLedger::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'type' => LoyaltyLedger::TYPE_REDEEM,
                'amount' => -$cost,
                'description' => "Redeemed reward #{$rewardId}",
            ]);
        });
    }
}
