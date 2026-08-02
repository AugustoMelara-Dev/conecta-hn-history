<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Global;

use App\Infrastructure\Billing\Global\ImmutableCreditLedger;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class NoCommissionCustodyTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_billing_slice_preserves_historical_payment_ledgers_and_never_materializes_buyer_merchant_custody(): void
    {
        $tenant = Tenant::factory()->create();

        self::assertTrue(class_exists(ImmutableCreditLedger::class));

        $ledger = new ImmutableCreditLedger;
        $ledger->adjust((string) $tenant->getKey(), 5.0, 'monthly_allocation', 'grant-1', [
            'allowance_credits' => 5.0,
        ]);
        $reservation = $ledger->reserve((string) $tenant->getKey(), 1.0, 'private_agent', 'reserve-1');
        $ledger->settle((string) $tenant->getKey(), $reservation->reservationId(), 1.0, 'provider-run-1');

        self::assertSame(0, DB::table('subscription_payments')->count());
        self::assertSame(0, DB::table('subscription_intents')->count());
        self::assertSame(0, DB::table('tenant_billing_references')->count());
        self::assertSame(3, DB::table('global_billing_credit_ledger_entries')->where('tenant_id', $tenant->id)->count());
    }
}
