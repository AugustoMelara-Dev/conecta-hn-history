<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use App\Application\Loyalty\Commands\RedeemLoyaltyRewardCommand;
use App\Application\Loyalty\Contracts\LoyaltyRedemptionPortInterface;
use App\Application\Loyalty\Handlers\RedeemLoyaltyRewardHandler;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RedeemLoyaltyRewardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_throws_when_the_reward_is_unavailable(): void
    {
        $port = new StubLoyaltyRedemptionPort(
            rewardCost: null,
            userBalance: 0,
        );

        $handler = new RedeemLoyaltyRewardHandler($port);
        $command = new RedeemLoyaltyRewardCommand('tenant-1', 1, 999);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Reward is unavailable or does not exist.');

        $handler->handle($command);
    }

    #[Test]
    public function it_throws_when_the_user_balance_is_insufficient(): void
    {
        $port = new StubLoyaltyRedemptionPort(
            rewardCost: 100,
            userBalance: 50,
        );

        $handler = new RedeemLoyaltyRewardHandler($port);
        $command = new RedeemLoyaltyRewardCommand('tenant-1', 1, 999);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Insufficient loyalty points.');

        $handler->handle($command);
    }

    #[Test]
    public function it_processes_redemptions_successfully(): void
    {
        $port = new StubLoyaltyRedemptionPort(
            rewardCost: 100,
            userBalance: 150,
        );

        $handler = new RedeemLoyaltyRewardHandler($port);
        $command = new RedeemLoyaltyRewardCommand('tenant-1', 1, 999);

        $handler->handle($command);

        self::assertSame([
            'user_id' => 1,
            'tenant_id' => 'tenant-1',
            'reward_id' => 999,
            'cost' => 100,
        ], $port->processedRedemption);
    }
}

final class StubLoyaltyRedemptionPort implements LoyaltyRedemptionPortInterface
{
    /** @var array{user_id: int, tenant_id: int|string, reward_id: int, cost: int}|null */
    public ?array $processedRedemption = null;

    public function __construct(
        private readonly ?int $rewardCost,
        private readonly int $userBalance,
    ) {}

    public function getRewardCostIfAvailable(int $rewardId, int|string $tenantId): ?int
    {
        return $this->rewardCost;
    }

    public function getUserBalance(int $userId, int|string $tenantId): int
    {
        return $this->userBalance;
    }

    public function processRedemption(int $userId, int|string $tenantId, int $rewardId, int $cost): void
    {
        $this->processedRedemption = [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'reward_id' => $rewardId,
            'cost' => $cost,
        ];
    }
}
