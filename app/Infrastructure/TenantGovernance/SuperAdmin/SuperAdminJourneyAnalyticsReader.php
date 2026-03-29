<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\SuperAdmin;

use App\Domain\TenantGovernance\Services\BusinessSegmentResolver;
use App\Models\CustomerConversation;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SuperAdminJourneyAnalyticsReader
{
    public function __construct(
        private readonly BusinessSegmentResolver $segmentResolver,
    ) {}

    public function verticalJourneyUplift(string $href): array
    {
        $window = now()->subDays(30)->startOfDay();
        $conversations = CustomerConversation::query()
            ->withoutGlobalScopes()
            ->with([
                'customerProfile' => fn ($query) => $query->withoutGlobalScopes()->with('tenant'),
            ])
            ->where('status', 'closed')
            ->where('closed_at', '>=', $window)
            ->get(['id', 'tenant_id', 'customer_profile_id', 'source_channel', 'metadata', 'closed_at']);

        $touchpoints = CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('created_at', '>=', $window)
            ->whereNotNull('customer_conversation_id')
            ->get(['id', 'customer_conversation_id', 'channel', 'metadata']);

        return $conversations
            ->groupBy(function (CustomerConversation $conversation): string {
                $tenant = $conversation->customerProfile?->tenant;

                return $tenant instanceof Tenant ? $this->segmentResolver->resolveForTenant($tenant) : 'retail_general';
            })
            ->map(function (Collection $items, string $segmentKey) use ($touchpoints, $href): array {
                $conversationIds = $items->map(fn (CustomerConversation $conversation): string => (string) $conversation->getKey())->all();
                $verticalTouchpoints = $touchpoints->filter(
                    fn (CustomerTouchpoint $touchpoint): bool => in_array((string) $touchpoint->customer_conversation_id, $conversationIds, true)
                );
                $assisted = $items->filter(fn (CustomerConversation $conversation): bool => $this->conversationHasJourneyAssistance($conversation));
                $assistedRevenue = $assisted->sum(fn (CustomerConversation $conversation): float => $this->conversationRevenueValue($conversation));
                $totalRevenue = $items->sum(fn (CustomerConversation $conversation): float => $this->conversationRevenueValue($conversation));
                $uplift = $totalRevenue > 0 ? number_format(($assistedRevenue / $totalRevenue) * 100, 1).'%' : '0.0%';

                return [
                    'vertical' => $this->segmentLabel($segmentKey),
                    'assisted_revenue' => 'L. '.number_format($assistedRevenue, 2),
                    'assisted_revenue_raw' => $assistedRevenue,
                    'total_revenue' => 'L. '.number_format($totalRevenue, 2),
                    'uplift' => $uplift,
                    'conversations' => $items->count(),
                    'assisted' => $assisted->count(),
                    'top_path' => $this->topConversationPathLabel($verticalTouchpoints),
                    'tone' => $assistedRevenue > 0 ? 'success' : ($items->count() > 0 ? 'warning' : 'gray'),
                    'href' => $href,
                ];
            })
            ->sortByDesc(fn (array $item): float => ((float) ($item['assisted_revenue_raw'] ?? 0.0) * 1000) + ((int) ($item['assisted'] ?? 0) * 100) + (int) ($item['conversations'] ?? 0))
            ->take(6)
            ->values()
            ->all();
    }

    public function networkJourneyPaths(string $href): array
    {
        $window = now()->subDays(30)->startOfDay();
        $touchpoints = CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->with([
                'conversation' => fn ($query) => $query->withoutGlobalScopes()->with([
                    'customerProfile' => fn ($profileQuery) => $profileQuery->withoutGlobalScopes()->with('tenant'),
                    'owner' => fn ($ownerQuery) => $ownerQuery->withoutGlobalScopes(),
                ]),
            ])
            ->where('created_at', '>=', $window)
            ->whereNotNull('customer_conversation_id')
            ->get(['id', 'customer_conversation_id', 'channel', 'metadata']);

        return collect($this->buildConversationPathShares($touchpoints))
            ->map(function (array $share) use ($href): array {
                return [
                    'path' => (string) ($share['label'] ?? 'Ruta CRM'),
                    'revenue' => 'L. '.number_format((float) ($share['revenue'] ?? 0.0), 2),
                    'vertical' => (string) ($share['vertical'] ?? 'General'),
                    'owners' => (string) ($share['owners'] ?? 'Sin owner'),
                    'channels' => (string) ($share['channels'] ?? 'CRM'),
                    'conversations' => (int) ($share['conversations'] ?? 0),
                    'revenue_raw' => (float) ($share['revenue'] ?? 0.0),
                    'tone' => (float) ($share['revenue'] ?? 0.0) > 0 ? 'success' : 'info',
                    'href' => $href,
                ];
            })
            ->sortByDesc(fn (array $row): float => ((float) ($row['revenue_raw'] ?? 0.0) * 1000) + ((int) ($row['conversations'] ?? 0) * 100))
            ->take(6)
            ->values()
            ->all();
    }

    private function segmentLabel(string $segmentKey): string
    {
        $profile = $this->segmentResolver->internalProfile($segmentKey, 'retail');
        $label = $profile['label'] ?? null;

        return is_string($label) && $label !== '' ? $label : Str::of($segmentKey === '' ? 'general' : $segmentKey)->replace('_', ' ')->title()->value();
    }

    private function conversationRevenueValue(CustomerConversation $conversation): float
    {
        $profile = $conversation->customerProfile;
        if (! $profile instanceof CustomerProfile) {
            return 0.0;
        }
        $firstConversion = (float) ($profile->first_conversion_value ?? 0);
        if ($firstConversion > 0) {
            return $firstConversion;
        }
        $initialLtv = (float) ($profile->initial_ltv ?? 0);
        if ($initialLtv > 0) {
            return $initialLtv;
        }

        return (float) ($profile->total_spent ?? 0);
    }

    private function conversationHasJourneyAssistance(CustomerConversation $conversation): bool
    {
        $signals = (array) data_get($conversation->metadata, 'business_signals', []);

        return $signals !== [] || ($conversation->source_channel !== null && $conversation->source_channel !== '');
    }

    private function buildConversationPathShares(Collection $touchpoints): array
    {
        $shares = [];
        $touchpoints->groupBy(fn (CustomerTouchpoint $touchpoint): string => (string) ($touchpoint->customer_conversation_id ?? ''))
            ->each(function (Collection $items) use (&$shares): void {
                $conversation = $items->first()?->conversation;
                if (! $conversation instanceof CustomerConversation || $conversation->status !== 'closed') {
                    return;
                }
                $path = $items->sortBy('id')->map(fn (CustomerTouchpoint $touchpoint): string => (string) data_get($touchpoint->metadata, 'journey_sequence_label', data_get($touchpoint->metadata, 'journey_step_title', $touchpoint->template_label)))->filter()->unique()->values();
                if ($path->isEmpty()) {
                    return;
                }
                $tenant = $conversation->customerProfile?->tenant;
                $label = $path->implode(' -> ');
                $channels = $items->pluck('channel')->filter()->map(fn (string $channel): string => Str::of($channel)->replace('_', ' ')->title()->value())->unique()->implode(', ');
                $segmentLabel = $tenant instanceof Tenant ? $this->segmentLabel($this->segmentResolver->resolveForTenant($tenant)) : 'General';
                $shares[$label] ??= [
                    'label' => $label,
                    'revenue' => 0.0,
                    'vertical' => $segmentLabel,
                    'owners' => $conversation->owner?->name ?? 'Sin owner',
                    'channels' => $channels !== '' ? $channels : 'CRM',
                    'conversations' => 0,
                ];
                $shares[$label]['revenue'] += $this->conversationRevenueValue($conversation);
                $shares[$label]['conversations']++;
            });

        return $shares;
    }

    private function topConversationPathLabel(Collection $touchpoints): string
    {
        $shares = collect($this->buildConversationPathShares($touchpoints));
        if ($shares->isEmpty()) {
            return 'Sin ruta dominante';
        }
        $top = $shares->sortByDesc(fn (array $share): float => ((float) ($share['revenue'] ?? 0.0) * 1000) + (int) ($share['conversations'] ?? 0))->first();

        return is_array($top) ? (string) ($top['label'] ?? 'Sin ruta dominante') : 'Sin ruta dominante';
    }
}
