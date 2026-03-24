<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Exceptions\InsufficientAiCreditsException;
use App\Infrastructure\AI\BudgetEnforcer;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class BudgetEnforcerTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private BudgetEnforcer $budgetEnforcer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'ai_balance' => 1.000000,
            'ai_reserved_balance' => 0.000000,
        ]);

        $this->budgetEnforcer = new BudgetEnforcer;
    }

    #[Test]
    public function reserve_increments_ai_reserved_balance_when_sufficient_funds(): void
    {
        $reservationId = $this->budgetEnforcer->reserve(
            tenantId: (string) $this->tenant->id,
            estimatedCost: 0.50,
            operation: 'test_operation',
        );

        self::assertSame(16, strlen($reservationId));

        $this->tenant->refresh();

        self::assertEqualsWithDelta(0.50, (float) $this->tenant->ai_reserved_balance, 0.000001);
        self::assertEqualsWithDelta(1.00, (float) $this->tenant->ai_balance, 0.000001);
    }

    #[Test]
    public function reserve_throws_when_balance_is_too_low(): void
    {
        $this->expectException(InsufficientAiCreditsException::class);

        try {
            $this->budgetEnforcer->reserve(
                tenantId: (string) $this->tenant->id,
                estimatedCost: 5.00,
                operation: 'expensive_operation',
            );
        } finally {
            $this->tenant->refresh();

            self::assertEqualsWithDelta(0.00, (float) $this->tenant->ai_reserved_balance, 0.000001);
        }
    }

    #[Test]
    public function reserve_considers_already_reserved_balance(): void
    {
        $this->budgetEnforcer->reserve((string) $this->tenant->id, 0.60, 'first');

        $this->expectException(InsufficientAiCreditsException::class);

        $this->budgetEnforcer->reserve(
            tenantId: (string) $this->tenant->id,
            estimatedCost: 0.50,
            operation: 'second',
        );
    }

    #[Test]
    public function reserve_throws_for_non_existent_tenant(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant 999999 no encontrado');

        $this->budgetEnforcer->reserve(
            tenantId: 999999,
            estimatedCost: 0.10,
            operation: 'test',
        );
    }

    #[Test]
    public function settle_decrements_both_balances_correctly(): void
    {
        $reservationId = $this->budgetEnforcer->reserve((string) $this->tenant->id, 0.50, 'test');

        $this->budgetEnforcer->settle(
            tenantId: (string) $this->tenant->id,
            estimatedCost: 0.50,
            actualCost: 0.30,
            reservationId: $reservationId,
            usageMetadata: ['model' => 'gpt-4o'],
        );

        $this->tenant->refresh();

        self::assertEqualsWithDelta(0.00, (float) $this->tenant->ai_reserved_balance, 0.000001);
        self::assertEqualsWithDelta(0.70, (float) $this->tenant->ai_balance, 0.000001);
    }

    #[Test]
    public function refund_only_decrements_ai_reserved_balance(): void
    {
        $reservationId = $this->budgetEnforcer->reserve((string) $this->tenant->id, 0.50, 'test');

        $this->budgetEnforcer->refund(
            tenantId: (string) $this->tenant->id,
            estimatedCost: 0.50,
            reservationId: $reservationId,
            failureReason: 'API timeout',
        );

        $this->tenant->refresh();

        self::assertEqualsWithDelta(0.00, (float) $this->tenant->ai_reserved_balance, 0.000001);
        self::assertEqualsWithDelta(1.00, (float) $this->tenant->ai_balance, 0.000001);
    }

    #[Test]
    public function get_available_balance_returns_balance_minus_reserved(): void
    {
        $this->budgetEnforcer->reserve((string) $this->tenant->id, 0.30, 'test');

        $available = $this->budgetEnforcer->getAvailableBalance((string) $this->tenant->id);

        self::assertEqualsWithDelta(0.70, $available, 0.000001);
    }

    #[Test]
    public function get_available_balance_returns_zero_for_non_existent_tenant(): void
    {
        $available = $this->budgetEnforcer->getAvailableBalance(999999);

        self::assertSame(0.0, $available);
    }
}
