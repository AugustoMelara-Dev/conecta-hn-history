<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Application\Billing\Global\EntitlementServiceInterface;
use App\Application\Markets\MarketContext;
use App\Domain\Billing\Global\EntitlementDecision;
use App\Domain\Billing\Global\GlobalTenantContext;
use App\Domain\Billing\Global\PlanPrice;
use App\Domain\Billing\Global\PriceBook;
use App\Domain\Billing\Global\PriceBookReaderInterface;
use App\Domain\Billing\Global\WorkforceEntitlement;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WorkforceEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sandbox_capability_uses_the_existing_authority_without_live_evidence(): void
    {
        $authority = $this->allowingAuthority();

        self::assertTrue((new WorkforceEntitlement($authority, $this->priceBooks(0.0)))
            ->allows('workforce.sandbox', $this->context('explore', 'USD')));
    }

    public function test_live_capability_denies_when_market_currency_does_not_match_price_book(): void
    {
        self::assertFalse((new WorkforceEntitlement($this->allowingAuthority(), $this->priceBooks(25.0)))
            ->allows('workforce.live', $this->context('launch', 'HNL')));
    }

    public function test_live_capability_requires_current_plan_capacity_and_matching_verified_event(): void
    {
        $tenant = Tenant::factory()->create();
        $this->insertEntitlement((string) $tenant->id, includedCredits: 25.0);
        $this->insertLifecycleEvent((string) $tenant->id, replayed: false);

        self::assertTrue((new WorkforceEntitlement($this->allowingAuthority(), $this->priceBooks(25.0)))
            ->allows('workforce.live', $this->context('launch', 'USD', (string) $tenant->id)));
    }

    public function test_live_capability_denies_zero_capacity_replayed_events_and_incoherent_statuses(): void
    {
        $zeroCreditsTenant = Tenant::factory()->create();
        $this->insertEntitlement((string) $zeroCreditsTenant->id, includedCredits: 0.0);
        $this->insertLifecycleEvent((string) $zeroCreditsTenant->id, replayed: false);
        self::assertFalse((new WorkforceEntitlement($this->allowingAuthority(), $this->priceBooks(25.0)))
            ->allows('workforce.live', $this->context('launch', 'USD', (string) $zeroCreditsTenant->id)));

        $replayedTenant = Tenant::factory()->create();
        $this->insertEntitlement((string) $replayedTenant->id, includedCredits: 25.0);
        $this->insertLifecycleEvent((string) $replayedTenant->id, replayed: true);
        self::assertFalse((new WorkforceEntitlement($this->allowingAuthority(), $this->priceBooks(25.0)))
            ->allows('workforce.live', $this->context('launch', 'USD', (string) $replayedTenant->id)));

        $failedTenant = Tenant::factory()->create();
        $this->insertEntitlement((string) $failedTenant->id, includedCredits: 25.0, status: 'canceled');
        $this->insertLifecycleEvent((string) $failedTenant->id, eventType: 'invoice.payment_failed', replayed: false);
        self::assertFalse((new WorkforceEntitlement($this->allowingAuthority(), $this->priceBooks(25.0)))
            ->allows('workforce.live', $this->context('launch', 'USD', (string) $failedTenant->id)));

        $incoherentTenant = Tenant::factory()->create();
        $this->insertEntitlement((string) $incoherentTenant->id, includedCredits: 25.0);
        $this->insertLifecycleEvent((string) $incoherentTenant->id, eventType: 'invoice.payment_failed');
        self::assertFalse((new WorkforceEntitlement($this->allowingAuthority(), $this->priceBooks(25.0)))
            ->allows('workforce.live', $this->context('launch', 'USD', (string) $incoherentTenant->id)));
    }

    public function test_live_capability_denies_verified_events_with_incomplete_payloads(): void
    {
        $tenant = Tenant::factory()->create();
        $this->insertEntitlement((string) $tenant->id, includedCredits: 25.0);
        $this->insertLifecycleEvent((string) $tenant->id);
        DB::table('global_billing_lifecycle_events')->where('tenant_id', $tenant->id)->update([
            'payload' => json_encode([
                'tenant_id' => (string) $tenant->id,
                'event_type' => 'subscription.activated',
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertFalse((new WorkforceEntitlement($this->allowingAuthority(), $this->priceBooks(25.0)))
            ->allows('workforce.live', $this->context('launch', 'USD', (string) $tenant->id)));
    }

    public function test_live_capability_does_not_reactivate_from_an_older_event_processed_later(): void
    {
        $tenant = Tenant::factory()->create();
        $now = CarbonImmutable::now();
        $this->insertEntitlement(
            (string) $tenant->id,
            includedCredits: 25.0,
            status: 'canceled',
            eventId: 'evt-cancelled',
            occurredAt: $now->subMinute(),
            createdAt: $now->subMinute(),
        );
        $this->insertLifecycleEvent(
            (string) $tenant->id,
            eventType: 'subscription.canceled',
            eventId: 'evt-cancelled',
            occurredAt: $now->subMinute(),
            createdAt: $now->subMinute(),
        );
        $this->insertEntitlement(
            (string) $tenant->id,
            includedCredits: 25.0,
            eventId: 'evt-old-activation',
            occurredAt: $now->subMinutes(2),
            createdAt: $now,
        );
        $this->insertLifecycleEvent(
            (string) $tenant->id,
            eventId: 'evt-old-activation',
            occurredAt: $now->subMinutes(2),
            createdAt: $now,
        );

        self::assertFalse((new WorkforceEntitlement($this->allowingAuthority(), $this->priceBooks(25.0)))
            ->allows('workforce.live', $this->context('launch', 'USD', (string) $tenant->id)));
    }

    private function allowingAuthority(): EntitlementServiceInterface
    {
        $authority = $this->createMock(EntitlementServiceInterface::class);
        $authority->method('allows')
            ->willReturn(new EntitlementDecision(true, 'allowed', 'active'));

        return $authority;
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

    private function context(string $plan, string $currency, ?string $tenantId = null): GlobalTenantContext
    {
        return GlobalTenantContext::fromArray([
            'tenant_id' => $tenantId ?? (string) Str::uuid(),
            'market' => new MarketContext('HN', 'es-HN', $currency, 'America/Tegucigalpa', 'tenant_market'),
            'plan' => $plan,
            'status' => 'active',
        ]);
    }

    private function insertEntitlement(
        string $tenantId,
        float $includedCredits,
        string $status = 'active',
        string $providerKey = 'deterministic-fake',
        ?string $eventId = null,
        ?CarbonImmutable $occurredAt = null,
        ?CarbonImmutable $createdAt = null,
    ): void {
        $eventId ??= 'evt-workforce-'.$tenantId;
        $now = now();
        $occurredAt ??= CarbonImmutable::parse($now);
        $createdAt ??= CarbonImmutable::parse($now);
        DB::table('global_billing_entitlements')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'market_key' => 'hn',
            'price_book_version' => 'test-v1',
            'plan_key' => 'launch',
            'currency_code' => 'USD',
            'status' => $status,
            'sandbox_access' => false,
            'live_access' => $status !== 'canceled',
            'included_credits' => $includedCredits,
            'grace_ends_at' => null,
            'provider_key' => $providerKey,
            'provider_event_id' => $eventId,
            'snapshot' => json_encode(['plan' => 'launch', 'status' => $status, 'occurred_at' => $occurredAt->toIso8601String()], JSON_THROW_ON_ERROR),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function insertLifecycleEvent(
        string $tenantId,
        string $eventType = 'subscription.activated',
        bool $replayed = false,
        ?string $eventId = null,
        ?CarbonImmutable $occurredAt = null,
        ?CarbonImmutable $createdAt = null,
    ): void {
        $eventId ??= 'evt-workforce-'.$tenantId;
        $now = now();
        $occurredAt ??= CarbonImmutable::parse($now);
        $createdAt ??= CarbonImmutable::parse($now);
        DB::table('global_billing_lifecycle_events')->insert([
            'id' => (string) Str::uuid(),
            'market_key' => 'hn',
            'provider_key' => 'deterministic-fake',
            'provider_event_id' => $eventId,
            'event_type' => $eventType,
            'signature' => 'fixture-signature',
            'payload_sha256' => hash('sha256', 'fixture-payload'),
            'tenant_id' => $tenantId,
            'authenticity_status' => 'verified',
            'replayed' => $replayed,
            'processed_at' => $createdAt,
            'payload' => json_encode([
                'tenant_id' => $tenantId,
                'plan' => 'launch',
                'status' => 'active',
                'event_type' => $eventType,
                'occurred_at' => $occurredAt->toIso8601String(),
            ], JSON_THROW_ON_ERROR),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
