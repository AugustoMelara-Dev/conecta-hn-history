<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerConversation;
use App\Models\CustomerProfile;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

class CustomerJourneySignalRecorder
{
    public function __construct(
        private readonly CustomerJourneyManager $journeyManager,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordForProfile(
        CustomerProfile $profile,
        string $signalKey,
        array $payload = [],
        ?string $channel = null,
        ?Carbon $occurredAt = null,
    ): ?CustomerConversation {
        $conversation = $this->resolveProfileConversation($profile, $channel);

        if (! $conversation instanceof CustomerConversation) {
            return null;
        }

        return $this->recordForConversation($conversation, $profile, $signalKey, $payload, $occurredAt);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordForTenantActiveConversations(
        Tenant $tenant,
        string $signalKey,
        array $payload = [],
        ?Carbon $occurredAt = null,
    ): int {
        $recorded = 0;
        $timestamp = ($occurredAt ?? now())->toIso8601String();

        CustomerConversation::query()
            ->withoutGlobalScopes()
            ->with(['customerProfile' => fn ($query) => $query->withoutGlobalScopes()->with('tenant')])
            ->where('tenant_id', (string) $tenant->getKey())
            ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
            ->get()
            ->each(function (CustomerConversation $conversation) use ($signalKey, $payload, $timestamp, &$recorded): void {
                $profile = $conversation->customerProfile;

                if (! $profile instanceof CustomerProfile) {
                    return;
                }

                $this->persistSignal($conversation, $signalKey, $payload, $timestamp);
                $this->journeyManager->sync($conversation, $profile);
                $recorded++;
            });

        return $recorded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordForConversation(
        CustomerConversation $conversation,
        CustomerProfile $profile,
        string $signalKey,
        array $payload = [],
        ?Carbon $occurredAt = null,
    ): CustomerConversation {
        $timestamp = ($occurredAt ?? now())->toIso8601String();

        $this->persistSignal($conversation, $signalKey, $payload, $timestamp);
        $this->journeyManager->sync($conversation->fresh(), $profile);

        return $conversation->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistSignal(
        CustomerConversation $conversation,
        string $signalKey,
        array $payload,
        string $timestamp,
    ): void {
        $metadata = (array) $conversation->metadata;
        $signals = (array) ($metadata['business_signals'] ?? []);

        $signals[$signalKey] = [
            'at' => $timestamp,
            'payload' => $payload,
        ];

        $history = collect((array) ($metadata['signal_history'] ?? []))
            ->push([
                'key' => $signalKey,
                'at' => $timestamp,
                'payload' => $payload,
            ])
            ->take(-10)
            ->values()
            ->all();

        $conversation->forceFill([
            'metadata' => array_merge($metadata, [
                'business_signals' => $signals,
                'last_business_signal' => $signalKey,
                'last_business_signal_at' => $timestamp,
                'signal_history' => $history,
            ]),
        ])->save();
    }

    private function resolveProfileConversation(CustomerProfile $profile, ?string $channel = null): ?CustomerConversation
    {
        $query = CustomerConversation::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $profile->tenant_id)
            ->where('customer_profile_id', (int) $profile->getKey())
            ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
            ->latest('updated_at')
            ->latest('id');

        if ($channel !== null && $channel !== '') {
            $channelConversation = (clone $query)
                ->where('source_channel', $channel)
                ->first();

            if ($channelConversation instanceof CustomerConversation) {
                return $channelConversation;
            }
        }

        return $query->first();
    }
}
