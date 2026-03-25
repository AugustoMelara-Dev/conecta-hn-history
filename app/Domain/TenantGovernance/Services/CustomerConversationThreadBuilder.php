<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Collection;

class CustomerConversationThreadBuilder
{
    /**
     * @return array{summary: array{open_replies:int,pending_customer:int,last_customer_message:?string,last_team_message:?string}, items: list<array{kind:string,direction:string,title:string,body:string,channel:string,when:string,color:string}>}
     */
    public function build(CustomerProfile $profile, ?CustomerNote $note = null, int $limit = 16): array
    {
        $tenantId = (string) $profile->tenant_id;
        $profileId = (int) $profile->getKey();
        $conversationKey = $note?->conversation_key;
        $channel = $note?->source_channel;
        $windowHours = max(24, (int) config('customer-conversations.defaults.window_hours', 168));
        $since = now()->subHours($windowHours);

        $notes = CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->when($channel !== null, fn ($query) => $query->where('source_channel', $channel))
            ->when($conversationKey !== null, fn ($query) => $query->where('conversation_key', $conversationKey))
            ->where('created_at', '>=', $since)
            ->latest('created_at')
            ->limit($limit)
            ->get();

        $touchpoints = CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->when($channel !== null, fn ($query) => $query->where('channel', $channel))
            ->where('created_at', '>=', $since)
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->filter(function (CustomerTouchpoint $touchpoint) use ($conversationKey): bool {
                if ($conversationKey === null) {
                    return true;
                }

                return (string) data_get($touchpoint->metadata, 'conversation_key', '') === $conversationKey;
            });

        $items = Collection::make()
            ->concat($notes->map(fn (CustomerNote $threadNote): array => [
                'kind' => 'note',
                'direction' => 'customer',
                'title' => $threadNote->note_type_label.' del cliente',
                'body' => trim((string) $threadNote->body),
                'channel' => (string) ($threadNote->source_channel ?? 'crm'),
                'sort_at' => $threadNote->last_customer_message_at
                    ?? $threadNote->created_at
                    ?? now(),
                'color' => $threadNote->sla_color,
            ]))
            ->concat($touchpoints->map(fn (CustomerTouchpoint $touchpoint): array => [
                'kind' => 'touchpoint',
                'direction' => 'team',
                'title' => filled($touchpoint->subject)
                    ? (string) $touchpoint->subject
                    : 'Seguimiento '.($touchpoint->channel === 'whatsapp' ? 'WhatsApp' : 'email'),
                'body' => trim((string) ($touchpoint->message ?? $touchpoint->last_error ?? 'Seguimiento enviado.')),
                'channel' => (string) $touchpoint->channel,
                'sort_at' => $touchpoint->replied_at
                    ?? $touchpoint->opened_at
                    ?? $touchpoint->delivered_at
                    ?? $touchpoint->sent_at
                    ?? $touchpoint->created_at
                    ?? now(),
                'color' => match ($touchpoint->status) {
                    CustomerTouchpoint::STATUS_FAILED => 'danger',
                    CustomerTouchpoint::STATUS_SKIPPED => 'warning',
                    default => 'info',
                },
            ]))
            ->sortByDesc('sort_at')
            ->take($limit)
            ->values()
            ->map(fn (array $item): array => [
                'kind' => $item['kind'],
                'direction' => $item['direction'],
                'title' => $item['title'],
                'body' => $item['body'],
                'channel' => ucfirst((string) $item['channel']),
                'when' => $item['sort_at']->diffForHumans(),
                'color' => $item['color'],
            ])
            ->all();

        $openReplies = $notes->whereIn('status', ['open', 'in_progress'])->count();
        $pendingCustomer = $notes->where('status', 'pending_customer')->count();
        $lastCustomerMessage = $notes->sortByDesc(fn (CustomerNote $threadNote) => $threadNote->last_customer_message_at ?? $threadNote->created_at)->first();
        $lastTeamMessage = $touchpoints->sortByDesc(fn (CustomerTouchpoint $touchpoint) => $touchpoint->sent_at ?? $touchpoint->created_at)->first();

        return [
            'summary' => [
                'open_replies' => $openReplies,
                'pending_customer' => $pendingCustomer,
                'last_customer_message' => $lastCustomerMessage?->last_customer_message_at?->diffForHumans() ?? $lastCustomerMessage?->created_at?->diffForHumans(),
                'last_team_message' => $lastTeamMessage?->sent_at?->diffForHumans() ?? $lastTeamMessage?->created_at?->diffForHumans(),
            ],
            'items' => $items,
        ];
    }
}
