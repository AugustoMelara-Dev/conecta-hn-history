<?php

declare(strict_types=1);

namespace App\Application\Loyalty\Contracts;

interface LoyaltyRedemptionPortInterface
{
    public function getRewardCostIfAvailable(int $rewardId, int|string $tenantId): ?int;

    public function getUserBalance(int $userId, int|string $tenantId): int;

    public function processRedemption(int $userId, int|string $tenantId, int $rewardId, int $cost): void;
}
