<?php

declare(strict_types=1);

namespace App\Domain\Billing\Global;

use App\Application\Billing\Global\EntitlementServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final readonly class WorkforceEntitlement
{
    public function __construct(
        private EntitlementServiceInterface $authority,
        private PriceBookReaderInterface $priceBooks,
    ) {}

    public function allows(string $capability, GlobalTenantContext $context): bool
    {
        $capability = strtolower(trim($capability));
        if ($capability === '') {
            return false;
        }

        $billingCapability = BillingCapability::fromKey($capability);
        $decision = $this->authority->allows($context, $billingCapability);
        if (! $decision->allowed()) {
            return false;
        }

        // Live workforce execution needs both a plan with usable capacity and
        // a verifiable entitlement/provider event. Configuration alone is not
        // evidence that a live provider or subscription is available.
        if (! $billingCapability->requiresLiveEntitlement()) {
            return true;
        }

        try {
            $priceBook = $this->priceBooks->forMarket($context->market());
            if (! $priceBook->supportsCurrency($context->market()->currencyCode)) {
                return false;
            }

            $plan = $priceBook->plan($context->plan());
        } catch (\Throwable) {
            return false;
        }

        return $plan->includedCredits() > 0
            && $plan->liveEntitled()
            && $this->hasExternalEntitlementEvidence($context, $priceBook);
    }

    public function decision(string $capability, GlobalTenantContext $context): EntitlementDecision
    {
        if ($this->allows($capability, $context)) {
            return new EntitlementDecision(true, 'allowed', $context->status());
        }

        return new EntitlementDecision(false, 'workforce_entitlement_required', $context->status());
    }

    private function hasExternalEntitlementEvidence(GlobalTenantContext $context, PriceBook $priceBook): bool
    {
        if (! Schema::hasTable('global_billing_entitlements')
            || ! Schema::hasTable('global_billing_lifecycle_events')) {
            return false;
        }

        $marketKey = strtolower(trim($context->market()->countryCode));
        $entitlements = DB::table('global_billing_entitlements')
            ->where('tenant_id', $context->tenantId())
            ->where('market_key', $marketKey)
            ->where('created_at', '<=', now())
            ->get([
                'market_key',
                'price_book_version',
                'plan_key',
                'currency_code',
                'status',
                'live_access',
                'included_credits',
                'grace_ends_at',
                'provider_key',
                'provider_event_id',
                'snapshot',
                'created_at',
            ]);

        $entitlement = $this->latestOrderedEntitlement($entitlements);

        if ($entitlement === null
            || ! (bool) $entitlement->live_access
            || ! in_array(strtolower((string) $entitlement->status), ['active', 'trial', 'grace'], true)
            || ! hash_equals(strtolower((string) $entitlement->plan_key), strtolower($context->plan()))
            || ! hash_equals(strtoupper((string) $entitlement->currency_code), strtoupper($priceBook->currencyCode()))
            || ! hash_equals((string) $entitlement->price_book_version, $priceBook->version())
            || (float) $entitlement->included_credits <= 0
            || trim((string) $entitlement->provider_key) === ''
            || trim((string) $entitlement->provider_event_id) === '') {
            return false;
        }

        $status = strtolower((string) $entitlement->status);
        if ($status === 'grace') {
            if ($entitlement->grace_ends_at === null) {
                return false;
            }

            try {
                if (! CarbonImmutable::parse((string) $entitlement->grace_ends_at)->isFuture()) {
                    return false;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        $event = DB::table('global_billing_lifecycle_events')
            ->where('tenant_id', $context->tenantId())
            ->where('market_key', $marketKey)
            ->where('provider_key', (string) $entitlement->provider_key)
            ->where('provider_event_id', (string) $entitlement->provider_event_id)
            ->where('authenticity_status', 'verified')
            ->where('replayed', false)
            ->whereNotNull('processed_at')
            ->where('processed_at', '<=', now())
            ->orderByDesc('processed_at')
            ->first(['event_type', 'payload']);

        if ($event === null || ! $this->eventTypeSupportsStatus((string) $event->event_type, $status)) {
            return false;
        }

        $payload = json_decode((string) $event->payload, true);
        if (! is_array($payload)) {
            return false;
        }

        $entitlementSnapshot = json_decode((string) ($entitlement->snapshot ?? ''), true);
        $eventOccurredAt = $this->payloadOccurredAt($payload);
        $entitlementOccurredAt = $this->payloadOccurredAt($entitlementSnapshot);

        return isset($payload['tenant_id'], $payload['plan'], $payload['status'], $payload['event_type'])
            && hash_equals((string) $context->tenantId(), (string) $payload['tenant_id'])
            && hash_equals(strtolower($context->plan()), strtolower((string) $payload['plan']))
            && hash_equals($status, strtolower((string) $payload['status']))
            && hash_equals((string) $event->event_type, (string) $payload['event_type'])
            && $eventOccurredAt !== null
            && $entitlementOccurredAt !== null
            && $eventOccurredAt->equalTo($entitlementOccurredAt);
    }

    /** @param iterable<object> $entitlements */
    private function latestOrderedEntitlement(iterable $entitlements): ?object
    {
        $selected = null;
        $selectedOccurredAt = null;

        foreach ($entitlements as $candidate) {
            if (trim((string) ($candidate->provider_event_id ?? '')) === '') {
                continue;
            }

            $occurredAt = $this->payloadOccurredAt(json_decode((string) ($candidate->snapshot ?? ''), true));
            if ($occurredAt === null) {
                return null;
            }

            if ($selected === null
                || $occurredAt->greaterThan($selectedOccurredAt)
                || ($occurredAt->equalTo($selectedOccurredAt) && (string) $candidate->created_at > (string) $selected->created_at)) {
                $selected = $candidate;
                $selectedOccurredAt = $occurredAt;
            }
        }

        return $selected;
    }

    /** @param array<string, mixed>|mixed $payload */
    private function payloadOccurredAt(mixed $payload): ?CarbonImmutable
    {
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

    private function eventTypeSupportsStatus(string $eventType, string $status): bool
    {
        return match ($status) {
            'active', 'trial' => in_array($eventType, [
                'invoice.paid',
                'subscription.activated',
                'subscription.renewed',
                'subscription.resumed',
                'subscription.updated',
            ], true),
            'grace' => in_array($eventType, ['invoice.payment_failed', 'subscription.past_due'], true),
            default => false,
        };
    }
}
