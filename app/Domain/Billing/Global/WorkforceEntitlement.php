<?php

declare(strict_types=1);

namespace App\Domain\Billing\Global;

use App\Application\Billing\Global\EntitlementServiceInterface;
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
            $plan = $this->priceBooks->forMarket($context->market())->plan($context->plan());
        } catch (\Throwable) {
            return false;
        }

        return $plan->includedCredits() > 0
            && $this->hasExternalEntitlementEvidence($context->tenantId());
    }

    public function decision(string $capability, GlobalTenantContext $context): EntitlementDecision
    {
        if ($this->allows($capability, $context)) {
            return new EntitlementDecision(true, 'allowed', $context->status());
        }

        return new EntitlementDecision(false, 'workforce_entitlement_required', $context->status());
    }

    private function hasExternalEntitlementEvidence(string $tenantId): bool
    {
        if (Schema::hasTable('global_billing_entitlements')) {
            $entitlement = DB::table('global_billing_entitlements')
                ->where('tenant_id', $tenantId)
                ->orderByDesc('created_at')
                ->first(['provider_key', 'provider_event_id', 'live_access', 'status']);

            if ($entitlement !== null
                && (bool) $entitlement->live_access
                && in_array(strtolower((string) $entitlement->status), ['active', 'trial', 'grace'], true)
                && trim((string) $entitlement->provider_key) !== ''
                && trim((string) $entitlement->provider_event_id) !== '') {
                return true;
            }
        }

        if (! Schema::hasTable('global_billing_lifecycle_events')) {
            return false;
        }

        return DB::table('global_billing_lifecycle_events')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('provider_key')
            ->whereNotNull('processed_at')
            ->whereIn('authenticity_status', ['verified', 'accepted', 'processed', 'valid'])
            ->exists();
    }
}
