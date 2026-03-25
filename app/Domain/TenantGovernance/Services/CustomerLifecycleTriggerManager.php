<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Order;
use App\Models\Tenant;

class CustomerLifecycleTriggerManager
{
    public function __construct(
        private readonly CustomerAutomationManager $automationManager,
    ) {}

    public function handleOrderSaved(Order $order): ?CustomerTouchpoint
    {
        $profile = $this->resolveProfile($order->getAttribute('customer_profile_id'));

        if (! $profile instanceof CustomerProfile) {
            return null;
        }

        if ($order->status === Order::STATUS_CANCELLED) {
            return null;
        }

        $journey = $profile->orders_count >= 2
            ? 'repeat_purchase_reward'
            : 'purchase_follow_up';

        return $this->dispatchJourney($profile, $journey);
    }

    public function handleAppointmentSaved(Appointment $appointment): ?CustomerTouchpoint
    {
        $profile = $this->resolveProfile($appointment->getAttribute('customer_profile_id'));

        if (! $profile instanceof CustomerProfile) {
            return null;
        }

        if (in_array($appointment->status, [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW], true)) {
            return null;
        }

        return $this->dispatchJourney($profile, 'appointment_follow_up');
    }

    /**
     * @return array{processed: int, sent: int, skipped: int, failed: int}
     */
    public function runReactivationSweep(Tenant|string $tenant, ?string $actorUserId = null, int $limit = 25): array
    {
        $tenantModel = $tenant instanceof Tenant
            ? $tenant
            : Tenant::query()->withoutGlobalScopes()->findOrFail($tenant);

        $processed = 0;
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        $profiles = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenantModel->getKey())
            ->where('reactivation_score', '>=', 65)
            ->orderByDesc('reactivation_score')
            ->orderByDesc('last_seen_at')
            ->limit(max(1, $limit))
            ->get();

        foreach ($profiles as $profile) {
            $processed++;

            $touchpoint = $this->dispatchJourney($profile, 'reactivation', $actorUserId);

            if (! $touchpoint instanceof CustomerTouchpoint) {
                $skipped++;

                continue;
            }

            $sent += $touchpoint->status === CustomerTouchpoint::STATUS_SENT ? 1 : 0;
            $skipped += $touchpoint->status === CustomerTouchpoint::STATUS_SKIPPED ? 1 : 0;
            $failed += $touchpoint->status === CustomerTouchpoint::STATUS_FAILED ? 1 : 0;
        }

        return compact('processed', 'sent', 'skipped', 'failed');
    }

    public function triggerJourney(
        CustomerProfile|int|string $profile,
        string $journeyKey,
        ?string $actorUserId = null,
    ): ?CustomerTouchpoint {
        $profileModel = $profile instanceof CustomerProfile
            ? $profile->withoutRelations()
            : $this->resolveProfile($profile);

        if (! $profileModel instanceof CustomerProfile) {
            return null;
        }

        return $this->dispatchJourney($profileModel, $journeyKey, $actorUserId);
    }

    private function dispatchJourney(
        CustomerProfile $profile,
        string $journeyKey,
        ?string $actorUserId = null,
    ): ?CustomerTouchpoint {
        $profile->loadMissing('tenant');

        $channel = $this->resolveChannel($profile);
        $automationKey = "{$journeyKey}_{$channel}";

        if ($this->wasRecentlyTriggered($profile, $automationKey, $channel)) {
            return null;
        }

        $touchpoint = $this->automationManager->sendSuggestedAutomation(
            profile: $profile,
            automationKey: $automationKey,
            actorUserId: $actorUserId,
            channel: $channel,
        );

        if ($touchpoint->status === CustomerTouchpoint::STATUS_SENT) {
            $profile->forceFill([
                'last_engaged_channel' => $channel,
                'last_reactivated_at' => $journeyKey === 'reactivation' ? now() : $profile->last_reactivated_at,
            ])->save();
        }

        return $touchpoint;
    }

    private function resolveProfile(mixed $profileId): ?CustomerProfile
    {
        if (! is_numeric($profileId)) {
            return null;
        }

        return CustomerProfile::query()
            ->withoutGlobalScopes()
            ->find((int) $profileId);
    }

    private function resolveChannel(CustomerProfile $profile): string
    {
        if ($profile->preferred_channel === 'whatsapp' && filled($profile->phone) && $profile->whatsapp_opt_in) {
            return 'whatsapp';
        }

        if (filled($profile->email) && $profile->email_opt_in) {
            return 'email';
        }

        if (filled($profile->phone) && $profile->whatsapp_opt_in) {
            return 'whatsapp';
        }

        return filled($profile->email) ? 'email' : 'whatsapp';
    }

    private function wasRecentlyTriggered(CustomerProfile $profile, string $automationKey, string $channel): bool
    {
        return CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('customer_profile_id', (int) $profile->getKey())
            ->where('automation_key', $automationKey)
            ->where('channel', $channel)
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();
    }
}
