<?php

declare(strict_types=1);

namespace App\Application\Loyalty\Handlers;

use App\Application\Loyalty\Commands\RedeemLoyaltyRewardCommand;
use App\Application\Loyalty\Contracts\LoyaltyRedemptionPortInterface;
use DomainException;

final class RedeemLoyaltyRewardHandler
{
    public function __construct(
        private readonly LoyaltyRedemptionPortInterface $redemptionPort
    ) {}

    public function handle(RedeemLoyaltyRewardCommand $command): void
    {
        $cost = $this->redemptionPort->getRewardCostIfAvailable($command->rewardId, $command->tenantId);

        if ($cost === null) {
            throw new DomainException('Reward is unavailable or does not exist.');
        }

        $balance = $this->redemptionPort->getUserBalance($command->userId, $command->tenantId);

        if ($balance < $cost) {
            throw new DomainException('Insufficient loyalty points.');
        }

        $this->redemptionPort->processRedemption($command->userId, $command->tenantId, $command->rewardId, $cost);
    }
}
