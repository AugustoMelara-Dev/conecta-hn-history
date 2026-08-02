<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Global;

use App\Domain\Billing\Global\CreditLedgerInterface;
use App\Infrastructure\Billing\Global\ImmutableCreditLedger;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CreditConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservations_are_idempotent_and_cannot_overspend_the_same_tenant_account(): void
    {
        $tenant = Tenant::factory()->create();

        self::assertTrue(interface_exists(CreditLedgerInterface::class));
        self::assertTrue(class_exists(ImmutableCreditLedger::class));

        $ledger = new ImmutableCreditLedger;
        $ledger->adjust((string) $tenant->getKey(), 10.5, 'monthly_allocation', 'grant-1', [
            'allowance_credits' => 10.0,
        ]);

        $first = $ledger->reserve((string) $tenant->getKey(), 7.0, 'private_agent', 'reserve-1');
        $replayed = $ledger->reserve((string) $tenant->getKey(), 7.0, 'private_agent', 'reserve-1');

        self::assertSame($first->reservationId(), $replayed->reservationId());
        self::assertSame(1, DB::table('global_billing_credit_reservations')->where('tenant_id', $tenant->id)->count());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('insufficient');

        $ledger->reserve((string) $tenant->getKey(), 7.0, 'private_agent', 'reserve-2');
    }

    public function test_provider_overruns_are_reconciled_without_surprise_charges_and_failures_refund_in_full(): void
    {
        $tenant = Tenant::factory()->create();

        self::assertTrue(class_exists(ImmutableCreditLedger::class));

        $ledger = new ImmutableCreditLedger;
        $ledger->adjust((string) $tenant->getKey(), 10.5, 'monthly_allocation', 'grant-1', [
            'allowance_credits' => 10.0,
        ]);

        $reservation = $ledger->reserve((string) $tenant->getKey(), 7.0, 'private_agent', 'reserve-1');
        $settlement = $ledger->settle(
            (string) $tenant->getKey(),
            $reservation->reservationId(),
            9.0,
            'provider-run-1',
            ['provider_usage_credits' => 9.0],
        );

        self::assertSame(7.0, $settlement->chargedCredits());
        self::assertSame(2.0, $settlement->varianceCredits());
        self::assertSame('provider_overrun_absorbed', $settlement->reconciliationStatus());

        $secondReservation = $ledger->reserve((string) $tenant->getKey(), 2.0, 'catalog_vision', 'reserve-2');
        $ledger->settle(
            (string) $tenant->getKey(),
            $secondReservation->reservationId(),
            2.0,
            'provider-run-2',
            ['provider_usage_credits' => 2.0],
        );

        $thirdReservation = $ledger->reserve((string) $tenant->getKey(), 1.0, 'catalog_vision', 'reserve-3');
        $ledger->settle(
            (string) $tenant->getKey(),
            $thirdReservation->reservationId(),
            1.0,
            'provider-run-3',
            ['provider_usage_credits' => 1.0],
        );

        $failedReservation = $ledger->reserve((string) $tenant->getKey(), 0.5, 'catalog_vision', 'reserve-4');
        $refund = $ledger->refund(
            (string) $tenant->getKey(),
            $failedReservation->reservationId(),
            'provider_unavailable',
            'refund-1',
        );

        self::assertSame('refunded', $refund->status());
        self::assertSame([70, 90, 100], DB::table('global_billing_credit_alerts')
            ->where('tenant_id', $tenant->id)
            ->orderBy('threshold')
            ->pluck('threshold')
            ->map(static fn ($value): int => (int) $value)
            ->all());
    }
}
