<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Application\Billing\Global\EntitlementServiceInterface;
use App\Application\Markets\MarketContext;
use App\Domain\Billing\Global\BillingCapability;
use App\Domain\Billing\Global\EntitlementDecision;
use App\Domain\Billing\Global\GlobalTenantContext;
use App\Domain\Billing\Global\PlanPrice;
use App\Domain\Billing\Global\PriceBook;
use App\Domain\Billing\Global\PriceBookReaderInterface;
use App\Domain\Billing\Global\WorkforceEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkforceEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sandbox_capability_uses_the_existing_entitlement_authority(): void
    {
        $authority = $this->createMock(EntitlementServiceInterface::class);
        $authority->expects(self::once())
            ->method('allows')
            ->with(self::isInstanceOf(GlobalTenantContext::class), self::isInstanceOf(BillingCapability::class))
            ->willReturn(new EntitlementDecision(true, 'allowed', 'active'));
        $priceBooks = $this->priceBooks(0.0);
        $context = $this->context('explore');

        self::assertTrue((new WorkforceEntitlement($authority, $priceBooks))->allows('workforce.sandbox', $context));
    }

    public function test_live_capability_denies_without_provider_or_external_entitlement_evidence(): void
    {
        $authority = $this->createMock(EntitlementServiceInterface::class);
        $authority->method('allows')
            ->willReturn(new EntitlementDecision(true, 'allowed', 'active'));
        $context = $this->context('launch');

        self::assertFalse((new WorkforceEntitlement($authority, $this->priceBooks(25.0)))
            ->allows('workforce.live', $context));
    }

    private function priceBooks(float $credits): PriceBookReaderInterface
    {
        $reader = $this->createMock(PriceBookReaderInterface::class);
        $reader->method('forMarket')->willReturn(new PriceBook(
            'test-v1',
            'hn',
            'USD',
            [
                'explore' => new PlanPrice('explore', 'USD', 0, 0, true, false, 0.0, ['ai.sandbox_agent']),
                'launch' => new PlanPrice('launch', 'USD', 4900, 48804, false, true, $credits, ['ai.live_agent']),
            ],
        ));

        return $reader;
    }

    private function context(string $plan): GlobalTenantContext
    {
        return GlobalTenantContext::fromArray([
            'tenant_id' => 'tenant-workforce-test',
            'market' => new MarketContext('HN', 'es-HN', 'USD', 'America/Tegucigalpa', 'tenant_market'),
            'plan' => $plan,
            'status' => 'active',
        ]);
    }
}
