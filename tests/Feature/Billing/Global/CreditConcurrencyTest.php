<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Global;

use App\Domain\Billing\Global\CreditLedgerInterface;
use App\Infrastructure\Billing\Global\ImmutableCreditLedger;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function test_reservations_and_ledger_entries_scope_reused_idempotency_keys_to_the_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $ledger = new ImmutableCreditLedger;

        $ledger->adjust((string) $tenantA->getKey(), 5.0, 'monthly_allocation', 'shared-key');
        $ledger->adjust((string) $tenantB->getKey(), 5.0, 'monthly_allocation', 'shared-key');

        $reservationA = $ledger->reserve((string) $tenantA->getKey(), 1.0, 'private_agent', 'shared-key');
        $reservationB = $ledger->reserve((string) $tenantB->getKey(), 1.0, 'private_agent', 'shared-key');

        self::assertNotSame($reservationA->reservationId(), $reservationB->reservationId());
        self::assertSame((string) $tenantA->getKey(), $reservationA->tenantId());
        self::assertSame((string) $tenantB->getKey(), $reservationB->tenantId());
        self::assertSame(2, DB::table('global_billing_credit_reservations')
            ->where('idempotency_key', 'shared-key')
            ->count());
        self::assertSame(2, DB::table('global_billing_credit_ledger_entries')
            ->where('idempotency_key', 'shared-key')
            ->count());
    }

    public function test_idempotency_indexes_are_composite_and_global_unique_indexes_are_removed(): void
    {
        $reservationIndexes = collect(Schema::getIndexes('global_billing_credit_reservations'));
        $ledgerIndexes = collect(Schema::getIndexes('global_billing_credit_ledger_entries'));

        self::assertTrue($reservationIndexes->contains(static fn (array $index): bool => $index['name'] === 'global_billing_credit_reservations_tenant_idempotency_unique'
            && $index['unique'] === true
            && $index['columns'] === ['tenant_id', 'idempotency_key']));
        self::assertFalse($reservationIndexes->contains(static fn (array $index): bool => $index['name'] === 'global_billing_credit_reservations_idempotency_key_unique'));
        self::assertTrue($ledgerIndexes->contains(static fn (array $index): bool => $index['name'] === 'global_billing_credit_ledger_entries_tenant_idempotency_unique'
            && $index['unique'] === true
            && $index['columns'] === ['tenant_id', 'idempotency_key']));
        self::assertFalse($ledgerIndexes->contains(static fn (array $index): bool => $index['name'] === 'global_billing_credit_ledger_entries_idempotency_key_unique'));
    }

    public function test_reservation_ids_are_uuid_format_and_malformed_uuid_access_fails_closed(): void
    {
        $tenant = Tenant::factory()->create();
        $ledger = new ImmutableCreditLedger;
        $ledger->adjust((string) $tenant->getKey(), 2.0, 'monthly_allocation', 'uuid-grant');

        $reservation = $ledger->reserve((string) $tenant->getKey(), 1.0, 'private_agent', 'uuid-reservation');

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $reservation->reservationId(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('reservation id must be a UUID');

        $ledger->settle((string) $tenant->getKey(), 'not-a-uuid', 1.0, 'provider-run-invalid-uuid');
    }

    public function test_wrong_tenant_cannot_settle_another_tenants_reservation_uuid(): void
    {
        $owner = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $ledger = new ImmutableCreditLedger;
        $ledger->adjust((string) $owner->getKey(), 2.0, 'monthly_allocation', 'settle-tenant-grant');
        $reservation = $ledger->reserve((string) $owner->getKey(), 1.0, 'private_agent', 'settle-tenant-reservation');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('reservation not found');

        $ledger->settle((string) $otherTenant->getKey(), $reservation->reservationId(), 1.0, 'provider-run-wrong-tenant');
    }

    public function test_wrong_tenant_cannot_refund_another_tenants_reservation_uuid(): void
    {
        $owner = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $ledger = new ImmutableCreditLedger;
        $ledger->adjust((string) $owner->getKey(), 2.0, 'monthly_allocation', 'refund-tenant-grant');
        $reservation = $ledger->reserve((string) $owner->getKey(), 1.0, 'private_agent', 'refund-tenant-reservation');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('reservation not found');

        $ledger->refund((string) $otherTenant->getKey(), $reservation->reservationId(), 'wrong-tenant', 'refund-wrong-tenant');
    }
}
