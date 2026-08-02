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
        $entitlement = DB::table('global_billing_entitlements')
            ->where('tenant_id', $context->tenantId())
            ->where('market_key', $marketKey)
            ->where('created_at', '<=', now())
            ->orderByDesc('created_at')
            ->first([
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
            ]);

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

        return (! isset($payload['tenant_id']) || hash_equals((string) $context->tenantId(), (string) $payload['tenant_id']))
            && (! isset($payload['plan']) || hash_equals(strtolower($context->plan()), strtolower((string) $payload['plan'])))
            && (! isset($payload['status']) || hash_equals($status, strtolower((string) $payload['status'])));
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
