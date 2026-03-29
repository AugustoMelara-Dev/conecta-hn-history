<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Readers;

use App\Models\CustomerConversation;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class TenantJourneyAnalyticsReader
{
    /**
     * @return array{
     *   templatePerformance:list<array<string,mixed>>,
     *   sequencePerformance:list<array<string,mixed>>,
     *   sequenceAttribution:list<array<string,mixed>>,
     *   conversationPathAttribution:list<array<string,mixed>>,
     *   ownerSequenceAttribution:list<array<string,mixed>>,
     *   locationSequenceAttribution:list<array<string,mixed>>,
     *   channelSequenceAttribution:list<array<string,mixed>>,
     *   sequenceOutcomeAttribution:list<array<string,mixed>>,
     *   upgradeAssistedRevenue30d:float,
     *   ownerConversationPerformance:list<array<string,mixed>>,
     *   locationConversationPerformance:list<array<string,mixed>>
     * }
     */
    public function summarize(Tenant $tenant): array
    {
        $tenantId = (string) $tenant->getKey();
        $window = now()->subDays(30)->startOfDay();

        $touchpoints = CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->with([
                'conversation' => fn ($query) => $query->withoutGlobalScopes()->with('customerProfile'),
                'customerProfile' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $window)
            ->whereNotNull('template_key')
            ->get([
                'id',
                'template_key',
                'channel',
                'replied_at',
                'customer_conversation_id',
                'customer_profile_id',
                'metadata',
                'delivery_status',
            ]);

        $sequenceRevenueShares = $this->buildAttributedSequenceShares($touchpoints);

        $templatePerformance = $touchpoints
            ->groupBy(fn (CustomerTouchpoint $touchpoint): string => (string) ($touchpoint->template_key ?: 'manual'))
            ->map(function (Collection $items, string $templateKey): array {
                $sent = $items->count();
                $replied = $items->filter(fn (CustomerTouchpoint $touchpoint): bool => $touchpoint->replied_at !== null)->count();
                $closedConversations = $items
                    ->map(function (CustomerTouchpoint $touchpoint): ?CustomerConversation {
                        $conversation = $touchpoint->conversation;

                        return $conversation instanceof CustomerConversation ? $conversation : null;
                    })
                    ->filter(fn (?CustomerConversation $conversation): bool => $conversation instanceof CustomerConversation && $conversation->status === 'closed')
                    ->unique(fn (CustomerConversation $conversation): int => (int) $conversation->getKey())
                    ->values();
                $conversions = $closedConversations->count();
                $revenue = $closedConversations->sum(fn (CustomerConversation $conversation): float => $this->conversationRevenueValue($conversation));
                $topChannel = (string) ($items->pluck('channel')->filter()->countBy()->sortDesc()->keys()->first() ?? 'crm');

                return [
                    'label' => $this->formatSourceLabel($templateKey),
                    'channel' => $this->formatSourceLabel($topChannel),
                    'sent' => $sent,
                    'replied' => $replied,
                    'conversions' => $conversions,
                    'revenue' => $this->formatCurrency($revenue),
                    'revenue_raw' => $revenue,
                    'replyRate' => $sent > 0 ? number_format(($replied / $sent) * 100, 1).'%' : '0.0%',
                    'closeRate' => $sent > 0 ? number_format(($conversions / $sent) * 100, 1).'%' : '0.0%',
                ];
            })
            ->sortByDesc(fn (array $row): float => ($row['revenue_raw'] * 1000) + ($row['conversions'] * 100) + ($row['replied'] * 10) + $row['sent'])
            ->take(4)
            ->values()
            ->all();

        $sequencePerformance = $touchpoints
            ->groupBy(fn (CustomerTouchpoint $touchpoint): string => (string) data_get($touchpoint->metadata, 'journey_sequence_key', $touchpoint->template_key ?: 'manual'))
            ->map(function (Collection $items, string $sequenceKey): array {
                $sent = $items->count();
                $replied = $items->filter(fn (CustomerTouchpoint $touchpoint): bool => $touchpoint->replied_at !== null)->count();
                $closedConversations = $items
                    ->map(function (CustomerTouchpoint $touchpoint): ?CustomerConversation {
                        $conversation = $touchpoint->conversation;

                        return $conversation instanceof CustomerConversation ? $conversation : null;
                    })
                    ->filter(fn (?CustomerConversation $conversation): bool => $conversation instanceof CustomerConversation && $conversation->status === 'closed')
                    ->unique(fn (CustomerConversation $conversation): int => (int) $conversation->getKey())
                    ->values();
                $conversions = $closedConversations->count();
                $revenue = $closedConversations->sum(fn (CustomerConversation $conversation): float => $this->conversationRevenueValue($conversation));
                $first = $items->first();
                $first = $first instanceof CustomerTouchpoint ? $first : null;

                return [
                    'label' => (string) data_get($first instanceof CustomerTouchpoint ? $first->metadata : null, 'journey_sequence_label', data_get($first instanceof CustomerTouchpoint ? $first->metadata : null, 'journey_step_title', $this->formatSourceLabel($sequenceKey))),
                    'branch' => (string) data_get($first instanceof CustomerTouchpoint ? $first->metadata : null, 'journey_branch_label', 'Ruta base'),
                    'lane' => $this->formatSourceLabel((string) data_get($first instanceof CustomerTouchpoint ? $first->metadata : null, 'journey_lane', 'follow_up')),
                    'sent' => $sent,
                    'replied' => $replied,
                    'conversions' => $conversions,
                    'revenue' => $this->formatCurrency($revenue),
                    'revenue_raw' => $revenue,
                    'replyRate' => $sent > 0 ? number_format(($replied / $sent) * 100, 1).'%' : '0.0%',
                    'closeRate' => $sent > 0 ? number_format(($conversions / $sent) * 100, 1).'%' : '0.0%',
                ];
            })
            ->sortByDesc(fn (array $row): float => ($row['revenue_raw'] * 1000) + ($row['conversions'] * 100) + ($row['replied'] * 10) + $row['sent'])
            ->take(4)
            ->values()
            ->all();

        $sequenceAttribution = collect($sequenceRevenueShares)
            ->map(function (array $share, string $sequenceKey): array {
                return [
                    'label' => (string) ($share['label'] ?? $this->formatSourceLabel($sequenceKey)),
                    'revenue' => $this->formatCurrency((float) ($share['revenue'] ?? 0.0)),
                    'replies' => (int) ($share['replies'] ?? 0),
                    'clicks' => (int) ($share['clicks'] ?? 0),
                    'conversions' => number_format((float) ($share['conversions'] ?? 0.0), 2),
                    'revenue_raw' => (float) ($share['revenue'] ?? 0.0),
                ];
            })
            ->sortByDesc(fn (array $row): float => ($row['revenue_raw'] * 1000) + ($row['clicks'] * 10) + $row['replies'])
            ->take(4)
            ->values()
            ->all();

        $conversationPathAttribution = collect($this->buildConversationPathShares($touchpoints))
            ->map(function (array $share): array {
                return [
                    'label' => (string) ($share['label'] ?? 'Ruta CRM'),
                    'revenue' => $this->formatCurrency((float) ($share['revenue'] ?? 0.0)),
                    'revenue_raw' => (float) ($share['revenue'] ?? 0.0),
                    'conversations' => (int) ($share['conversations'] ?? 0),
                    'owners' => (string) ($share['owners'] ?? 'Sin owner'),
                    'channels' => (string) ($share['channels'] ?? 'CRM'),
                ];
            })
            ->sortByDesc(fn (array $row): float => ($row['revenue_raw'] * 1000) + ($row['conversations'] * 100))
            ->take(4)
            ->values()
            ->all();

        $ownerSequenceAttribution = $this->buildAttributedDimensionRows($this->buildAttributedSequenceShares($touchpoints, 'owner'));
        $locationSequenceAttribution = $this->buildAttributedDimensionRows($this->buildAttributedSequenceShares($touchpoints, 'location'));
        $channelSequenceAttribution = $this->buildAttributedDimensionRows($this->buildAttributedSequenceShares($touchpoints, 'channel'));

        $outcomeShares = $this->buildOutcomeAttributedSequenceShares($touchpoints);
        $sequenceOutcomeAttribution = collect($outcomeShares)
            ->map(function (array $share): array {
                return [
                    'label' => (string) ($share['label'] ?? 'Secuencia CRM'),
                    'revenue' => $this->formatCurrency((float) ($share['revenue'] ?? 0.0)),
                    'purchaseClosures' => (int) ($share['purchase_closures'] ?? 0),
                    'noShowRecovered' => (int) ($share['no_show_recovered'] ?? 0),
                    'rewardRepurchases' => (int) ($share['reward_repurchases'] ?? 0),
                    'upgradeAssists' => (int) ($share['upgrade_assists'] ?? 0),
                    'revenue_raw' => (float) ($share['revenue'] ?? 0.0),
                ];
            })
            ->sortByDesc(fn (array $row): float => ($row['revenue_raw'] * 1000) + ($row['purchaseClosures'] * 100) + ($row['upgradeAssists'] * 10))
            ->take(4)
            ->values()
            ->all();

        $conversations = CustomerConversation::query()
            ->withoutGlobalScopes()
            ->with([
                'owner' => fn ($query) => $query->withoutGlobalScopes(),
                'location' => fn ($query) => $query->withoutGlobalScopes(),
                'customerProfile' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($window): void {
                $query->where('updated_at', '>=', $window)
                    ->orWhere('closed_at', '>=', $window)
                    ->orWhere('last_customer_message_at', '>=', $window);
            })
            ->get([
                'id', 'customer_profile_id', 'owner_user_id', 'location_id', 'status',
                'response_due_at', 'last_customer_message_at', 'first_response_at', 'closed_at',
            ]);

        return [
            'templatePerformance' => $templatePerformance,
            'sequencePerformance' => $sequencePerformance,
            'sequenceAttribution' => $sequenceAttribution,
            'conversationPathAttribution' => $conversationPathAttribution,
            'ownerSequenceAttribution' => $ownerSequenceAttribution,
            'locationSequenceAttribution' => $locationSequenceAttribution,
            'channelSequenceAttribution' => $channelSequenceAttribution,
            'sequenceOutcomeAttribution' => $sequenceOutcomeAttribution,
            'upgradeAssistedRevenue30d' => collect($outcomeShares)->sum(fn (array $share): float => (int) ($share['upgrade_assists'] ?? 0) > 0 ? (float) ($share['revenue'] ?? 0.0) : 0.0),
            'ownerConversationPerformance' => $this->buildConversationPerformanceRows($conversations, 'owner_user_id', 'ownerLabel', 'Sin owner'),
            'locationConversationPerformance' => $this->buildConversationPerformanceRows($conversations, 'location_id', 'locationLabel', 'Sin sede'),
        ];
    }

    private function formatCurrency(float $value): string
    {
        return 'L. '.number_format($value, 2);
    }

    private function formatSourceLabel(string $value): string
    {
        return str($value === '' ? 'manual' : $value)->replace('_', ' ')->replace('-', ' ')->title()->value();
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

    private function buildAttributedSequenceShares(Collection $touchpoints, ?string $dimension = null): array
    {
        $shares = [];
        $touchpoints->groupBy(fn (CustomerTouchpoint $touchpoint): string => (string) ($touchpoint->customer_conversation_id ?? ''))
            ->each(function (Collection $items) use (&$shares, $dimension): void {
                $conversation = $items->first()?->conversation;
                if (! $conversation instanceof CustomerConversation || $conversation->status !== 'closed') {
                    return;
                }
                $sequenceGroups = $items->groupBy(fn (CustomerTouchpoint $touchpoint): string => (string) data_get($touchpoint->metadata, 'journey_sequence_key', $touchpoint->template_key ?: 'manual'))
                    ->filter(fn (Collection $slice, string $key): bool => $key !== '');
                if ($sequenceGroups->isEmpty()) {
                    return;
                }
                $revenue = $this->conversationRevenueValue($conversation);
                $conversionShare = 1 / max(1, $sequenceGroups->count());
                $revenueShare = $revenue / max(1, $sequenceGroups->count());
                $sequenceGroups->each(function (Collection $slice, string $sequenceKey) use (&$shares, $conversionShare, $revenueShare, $conversation, $dimension): void {
                    $dimensionKey = $sequenceKey;
                    $context = '';
                    $label = (string) data_get($slice->first()?->metadata, 'journey_sequence_label', $this->formatSourceLabel($sequenceKey));
                    if ($dimension === 'owner') {
                        $label = $conversation->ownerLabel;
                        $dimensionKey = "{$label}|{$sequenceKey}";
                        $context = (string) data_get($slice->first()?->metadata, 'journey_sequence_label', $this->formatSourceLabel($sequenceKey));
                    } elseif ($dimension === 'location') {
                        $label = $conversation->locationLabel;
                        $dimensionKey = "{$label}|{$sequenceKey}";
                        $context = (string) data_get($slice->first()?->metadata, 'journey_sequence_label', $this->formatSourceLabel($sequenceKey));
                    } elseif ($dimension === 'channel') {
                        $label = $this->formatSourceLabel((string) ($conversation->source_channel ?: 'crm'));
                        $dimensionKey = "{$label}|{$sequenceKey}";
                        $context = (string) data_get($slice->first()?->metadata, 'journey_sequence_label', $this->formatSourceLabel($sequenceKey));
                    }
                    $shares[$dimensionKey] ??= ['label' => $label, 'revenue' => 0.0, 'replies' => 0, 'clicks' => 0, 'conversions' => 0.0, 'context' => $context];
                    $shares[$dimensionKey]['revenue'] += $revenueShare;
                    $shares[$dimensionKey]['replies'] += $slice->filter(fn (CustomerTouchpoint $touchpoint): bool => $touchpoint->replied_at !== null)->count();
                    $shares[$dimensionKey]['clicks'] += $slice->filter(fn (CustomerTouchpoint $touchpoint): bool => in_array($touchpoint->delivery_status, [CustomerTouchpoint::DELIVERY_CLICKED, CustomerTouchpoint::DELIVERY_READ], true))->count();
                    $shares[$dimensionKey]['conversions'] += $conversionShare;
                });
            });

        return $shares;
    }

    private function buildAttributedDimensionRows(array $shares): array
    {
        return collect($shares)->map(function (array $share): array {
            return [
                'name' => (string) ($share['label'] ?? 'Sin dato'),
                'revenue' => $this->formatCurrency((float) ($share['revenue'] ?? 0.0)),
                'replies' => (int) ($share['replies'] ?? 0),
                'clicks' => (int) ($share['clicks'] ?? 0),
                'conversions' => number_format((float) ($share['conversions'] ?? 0.0), 2),
                'revenue_raw' => (float) ($share['revenue'] ?? 0.0),
                'context' => (string) ($share['context'] ?? 'Secuencia CRM'),
            ];
        })->sortByDesc(fn (array $row): float => ($row['revenue_raw'] * 1000) + ($row['clicks'] * 10) + $row['replies'])->take(4)->values()->all();
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
                $label = $path->implode(' -> ');
                $channels = $items->pluck('channel')->filter()->map(fn (string $channel): string => $this->formatSourceLabel($channel))->unique()->implode(', ');
                $shares[$label] ??= ['label' => $label, 'revenue' => 0.0, 'conversations' => 0, 'owners' => $conversation->ownerLabel, 'channels' => $channels !== '' ? $channels : 'CRM'];
                $shares[$label]['revenue'] += $this->conversationRevenueValue($conversation);
                $shares[$label]['conversations']++;
            });

        return $shares;
    }

    private function buildOutcomeAttributedSequenceShares(Collection $touchpoints): array
    {
        $shares = [];
        $touchpoints->groupBy(fn (CustomerTouchpoint $touchpoint): string => (string) ($touchpoint->customer_conversation_id ?? ''))
            ->each(function (Collection $items) use (&$shares): void {
                $conversation = $items->first()?->conversation;
                if (! $conversation instanceof CustomerConversation || $conversation->status !== 'closed') {
                    return;
                }
                $sequenceGroups = $items->groupBy(fn (CustomerTouchpoint $touchpoint): string => (string) data_get($touchpoint->metadata, 'journey_sequence_key', $touchpoint->template_key ?: 'manual'))
                    ->filter(fn (Collection $slice, string $key): bool => $key !== '');
                if ($sequenceGroups->isEmpty()) {
                    return;
                }
                $signals = (array) data_get($conversation->metadata, 'business_signals', []);
                $revenue = $this->conversationRevenueValue($conversation);
                $revenueShare = $revenue / max(1, $sequenceGroups->count());
                $sequenceGroups->each(function (Collection $slice, string $sequenceKey) use (&$shares, $signals, $revenueShare): void {
                    $shares[$sequenceKey] ??= ['label' => (string) data_get($slice->first()?->metadata, 'journey_sequence_label', $this->formatSourceLabel($sequenceKey)), 'revenue' => 0.0, 'purchase_closures' => 0, 'no_show_recovered' => 0, 'reward_repurchases' => 0, 'upgrade_assists' => 0];
                    $shares[$sequenceKey]['revenue'] += $revenueShare;
                    $shares[$sequenceKey]['purchase_closures'] += array_key_exists('purchase_closed', $signals) ? 1 : 0;
                    $shares[$sequenceKey]['no_show_recovered'] += array_key_exists('no_show_recovered', $signals) ? 1 : 0;
                    $shares[$sequenceKey]['reward_repurchases'] += array_key_exists('reward_repurchase', $signals) ? 1 : 0;
                    $shares[$sequenceKey]['upgrade_assists'] += array_key_exists('tenant_upgrade', $signals) ? 1 : 0;
                });
            });

        return $shares;
    }

    private function buildConversationPerformanceRows(Collection $conversations, string $dimensionKey, string $labelAccessor, string $emptyLabel): array
    {
        return $conversations->pluck($dimensionKey)->map(fn (mixed $value): string => (string) ($value ?: 'none'))->unique()->values()
            ->map(function (string $key) use ($conversations, $dimensionKey, $labelAccessor, $emptyLabel): array {
                $slice = $conversations->filter(fn (CustomerConversation $conversation): bool => (string) ($conversation->{$dimensionKey} ?: 'none') === $key);
                $responded = $slice->filter(fn (CustomerConversation $conversation): bool => $conversation->first_response_at !== null && $conversation->last_customer_message_at !== null);
                $avgResponse = $responded->count() > 0 ? round((float) $responded->map(fn (CustomerConversation $conversation): int => max(0, (int) $conversation->last_customer_message_at?->diffInMinutes($conversation->first_response_at)))->avg(), 1) : 0.0;
                $closed = $slice->where('status', 'closed')->count();
                $revenue = $slice->filter(fn (CustomerConversation $conversation): bool => $conversation->status === 'closed')->sum(fn (CustomerConversation $conversation): float => $this->conversationRevenueValue($conversation));

                return [
                    'name' => $key === 'none' ? $emptyLabel : (string) data_get($slice->first(), $labelAccessor, $emptyLabel),
                    'total' => $slice->count(),
                    'closed' => $closed,
                    'conversions' => $closed,
                    'revenue' => $this->formatCurrency($revenue),
                    'revenue_raw' => $revenue,
                    'waiting' => $slice->where('status', 'waiting_customer')->count(),
                    'breached' => $slice->filter(fn (CustomerConversation $conversation): bool => $conversation->response_due_at !== null && $conversation->response_due_at->isPast())->count(),
                    'avgFirstResponse' => $avgResponse > 0 ? $avgResponse.' min' : 'Sin datos',
                    'conversionRate' => $slice->count() > 0 ? number_format(($closed / $slice->count()) * 100, 1).'%' : '0.0%',
                ];
            })->sortByDesc(fn (array $row): float => ($row['revenue_raw'] * 1000) + ($row['closed'] * 100) + ($row['breached'] * 10) + $row['total'])->take(4)->values()->all();
    }
}
