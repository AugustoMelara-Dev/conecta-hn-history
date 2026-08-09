<?php

declare(strict_types=1);

namespace App\Application\Billing\Global;

use App\Domain\Billing\Global\BillingCapability;
use App\Domain\Billing\Global\EntitlementDecision;
use App\Domain\Billing\Global\GlobalTenantContext;
use App\Domain\Billing\Global\PlanPrice;
use App\Domain\Billing\Global\PriceBookReaderInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GlobalEntitlementService implements EntitlementServiceInterface
{
    public function __construct(
        private readonly PriceBookReaderInterface $priceBooks,
    ) {}

    public function allows(GlobalTenantContext $tenant, BillingCapability $capability): EntitlementDecision
    {
        $state = $this->currentState($tenant);

        if ($capability->requiresLiveEntitlement() && ($state['plan']->sandboxOnly() || ! $state['plan']->liveEntitled())) {
            return new EntitlementDecision(false, 'sandbox_only', $state['status']);
        }

        if ($capability->requiresLiveEntitlement() && ! $state['event_order_verified']) {
            return new EntitlementDecision(false, 'event_order_unverified', $state['status']);
        }

        if (! $state['plan']->includesCapability($capability->key())) {
            return new EntitlementDecision(false, 'missing_capability', $state['status']);
        }

        if (! $capability->requiresLiveEntitlement()) {
            return new EntitlementDecision(true, 'allowed', $state['status']);
        }

        if (in_array($state['status'], ['suspended', 'expired', 'canceled'], true)) {
            return new EntitlementDecision(false, $state['status'], $state['status']);
        }

        if ($state['status'] === 'grace') {
            return new EntitlementDecision(true, 'grace_period', 'grace');
        }

        return new EntitlementDecision(true, 'allowed', $state['status']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function synchronizeFromWebhook(GlobalTenantContext $tenant, array $payload): void
    {
        foreach (['event_id', 'event_type', 'tenant_id', 'plan', 'status', 'provider'] as $required) {
            if (! isset($payload[$required]) || ! is_scalar($payload[$required]) || trim((string) $payload[$required]) === '') {
                throw new \RuntimeException('webhook payload is incomplete');
            }
        }

        if (! hash_equals((string) $tenant->tenantId(), (string) $payload['tenant_id'])) {
            throw new \RuntimeException('webhook payload tenant does not match the authenticated context');
        }

        $occurredAt = $this->payloadOccurredAt($payload);
        if ($occurredAt === null) {
            throw new \RuntimeException('webhook payload requires a valid occurred_at timestamp');
        }

        $book = $this->priceBooks->forMarket($tenant->market());
        $plan = $book->plan((string) ($payload['plan'] ?? $tenant->plan()));
        $status = strtolower(trim((string) ($payload['status'] ?? $tenant->status())));
        $payload['occurred_at'] = $occurredAt->toIso8601String();

        DB::table('global_billing_entitlements')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->tenantId(),
            'market_key' => strtolower($tenant->market()->countryCode),
            'price_book_version' => $book->version(),
            'plan_key' => $plan->key(),
            'currency_code' => $book->currencyCode(),
            'status' => $status,
            'sandbox_access' => $plan->sandboxOnly(),
            'live_access' => $plan->liveEntitled(),
            'included_credits' => $plan->includedCredits(),
            'grace_ends_at' => $payload['grace_ends_at'] ?? null,
            'provider_key' => isset($payload['provider']) ? (string) $payload['provider'] : null,
            'provider_event_id' => isset($payload['event_id']) ? (string) $payload['event_id'] : null,
            'snapshot' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        DB::table('global_billing_credit_accounts')->updateOrInsert(
            ['tenant_id' => $tenant->tenantId()],
            [
                'allowance_credits' => $plan->includedCredits(),
                'updated_at' => now()->utc(),
                'created_at' => now()->utc(),
            ],
        );
    }

    /**
     * @return array{plan:PlanPrice,status:string,event_order_verified:bool}
     */
    private function currentState(GlobalTenantContext $tenant): array
    {
        $book = $this->priceBooks->forMarket($tenant->market());
        $rows = DB::table('global_billing_entitlements')
            ->where('tenant_id', $tenant->tenantId())
            ->where('market_key', strtolower($tenant->market()->countryCode))
            ->get(['plan_key', 'status', 'provider_event_id', 'snapshot', 'created_at']);

        $row = null;
        $selectedOccurredAt = null;
        $eventOrderVerified = true;
        foreach ($rows as $candidate) {
            $providerEventId = trim((string) ($candidate->provider_event_id ?? ''));
            $occurredAt = $providerEventId === '' ? CarbonImmutable::parse((string) $candidate->created_at) : $this->payloadOccurredAt($candidate->snapshot);
            if ($providerEventId !== '' && $occurredAt === null) {
                $eventOrderVerified = false;

                continue;
            }

            if ($row === null || $occurredAt->greaterThan($selectedOccurredAt) || ($occurredAt->equalTo($selectedOccurredAt) && (string) $candidate->created_at > (string) $row->created_at)) {
                $row = $candidate;
                $selectedOccurredAt = $occurredAt;
            }
        }

        if ($row === null && $rows !== []) {
            $eventOrderVerified = false;
        }

        $planKey = is_string($row?->plan_key) && $row->plan_key !== ''
            ? $row->plan_key
            : $tenant->plan();
        $status = is_string($row?->status) && $row->status !== ''
            ? strtolower($row->status)
            : strtolower($tenant->status());

        return [
            'plan' => $book->plan($planKey),
            'status' => $status,
            'event_order_verified' => $eventOrderVerified,
        ];
    }

    /** @param array<string, mixed>|mixed $payload */
    private function payloadOccurredAt(mixed $payload): ?CarbonImmutable
    {
        if (! is_array($payload)) {
            $decoded = json_decode((string) $payload, true);
            $payload = is_array($decoded) ? $decoded : null;
        }

        $value = is_array($payload) ? ($payload['occurred_at'] ?? null) : null;
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $occurredAt = CarbonImmutable::parse($value);

            return $occurredAt->isFuture() ? null : $occurredAt;
        } catch (\Throwable) {
            return null;
        }
    }
}
