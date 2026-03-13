<?php

declare(strict_types=1);

namespace App\Application\Loyalty\Commands;

final readonly class RedeemLoyaltyRewardCommand
{
    public function __construct(
        public int|string $tenantId,
        public int $userId,
        public int $rewardId
    ) {}
}
