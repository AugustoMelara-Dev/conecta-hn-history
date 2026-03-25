<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerConversation;
use App\Models\CustomerConversationMessage;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Collection;

class CustomerConversationThreadBuilder
{
    /**
     * @return array{
     *     summary: array{
     *         open_replies:int,
     *         pending_customer:int,
     *         internal_notes:int,
     *         last_customer_message:?string,
     *         last_team_message:?string
     *     },
     *     items: list<array{kind:string,direction:string,title:string,body:string,channel:string,when:string,color:string,thread:?string}>,
     *     internal_notes: list<array{title:string,body:string,when:string,author:string}>
     * }
     */
    public function build(CustomerProfile $profile, ?CustomerNote $note = null, int $limit = 16): array
    {
        $conversation = $note?->conversation()
            ->withoutGlobalScopes()
            ->with(['messages.author'])
            ->first();

        if ($conversation instanceof CustomerConversation) {
            return $this->buildFromConversation($conversation, $limit);
        }

        return $this->legacyBuild($profile, $note, $limit);
    }

    /**
     * @return array{
     *     summary: array{
     *         open_replies:int,
     *         pending_customer:int,
     *         internal_notes:int,
     *         last_customer_message:?string,
     *         last_team_message:?string
     *     },
     *     items: list<array{kind:string,direction:string,title:string,body:string,channel:string,when:string,color:string,thread:?string}>,
     *     internal_notes: list<array{title:string,body:string,when:string,author:string}>
     * }
     */
    private function buildFromConversation(CustomerConversation $conversation, int $limit): array
    {
        $messages = $conversation->messages()
            ->withoutGlobalScopes()
            ->with('author')
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();

        $items = $messages
            ->whereIn('direction', ['customer', 'team'])
            ->map(function (CustomerConversationMessage $message): array {
                $isCustomer = $message->direction === 'customer';

                return [
                    'kind' => (string) $message->message_kind,
                    'direction' => (string) $message->direction,
                    'title' => $isCustomer
                        ? 'Mensaje del cliente'
                        : 'Respuesta del equipo',
                    'body' => trim((string) ($message->body ?? 'Sin contenido.')),
                    'channel' => ucfirst((string) ($message->channel ?? 'crm')),
                    'when' => $message->occurred_at?->diffForHumans() ?? 'Hace un momento',
                    'color' => $isCustomer ? 'warning' : 'info',
                    'thread' => $message->provider_thread_key,
                ];
            })
            ->values()
            ->all();

        $internalNotes = $messages
            ->where('direction', 'internal')
            ->map(function (CustomerConversationMessage $message): array {
                return [
                    'title' => 'Nota interna',
                    'body' => trim((string) ($message->body ?? 'Sin detalle.')),
                    'when' => $message->occurred_at?->diffForHumans() ?? 'Hace un momento',
                    'author' => (string) ($message->author?->name ?? 'Equipo'),
                ];
            })
            ->values()
            ->all();

        return [
            'summary' => [
                'open_replies' => $conversation->status === 'open' ? 1 : 0,
                'pending_customer' => $conversation->status === 'waiting_customer' ? 1 : 0,
                'internal_notes' => (int) $conversation->internal_note_count,
                'last_customer_message' => $conversation->last_customer_message_at?->diffForHumans(),
                'last_team_message' => $conversation->last_team_message_at?->diffForHumans(),
            ],
            'items' => $items,
            'internal_notes' => $internalNotes,
        ];
    }

    /**
     * @return array{
     *     summary: array{
     *         open_replies:int,
     *         pending_customer:int,
     *         internal_notes:int,
     *         last_customer_message:?string,
     *         last_team_message:?string
     *     },
     *     items: list<array{kind:string,direction:string,title:string,body:string,channel:string,when:string,color:string,thread:?string}>,
     *     internal_notes: list<array{title:string,body:string,when:string,author:string}>
     * }
     */
    private function legacyBuild(CustomerProfile $profile, ?CustomerNote $note = null, int $limit = 16): array
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
                'channel' => ucfirst((string) ($threadNote->source_channel ?? 'crm')),
                'when' => ($threadNote->last_customer_message_at ?? $threadNote->created_at ?? now())->diffForHumans(),
                'color' => $threadNote->sla_color,
                'thread' => $threadNote->conversation_key,
            ]))
            ->concat($touchpoints->map(fn (CustomerTouchpoint $touchpoint): array => [
                'kind' => 'touchpoint',
                'direction' => 'team',
                'title' => filled($touchpoint->subject)
                    ? (string) $touchpoint->subject
                    : 'Seguimiento '.($touchpoint->channel === 'whatsapp' ? 'WhatsApp' : 'email'),
                'body' => trim((string) ($touchpoint->message ?? $touchpoint->last_error ?? 'Seguimiento enviado.')),
                'channel' => ucfirst((string) $touchpoint->channel),
                'when' => ($touchpoint->replied_at
                    ?? $touchpoint->opened_at
                    ?? $touchpoint->delivered_at
                    ?? $touchpoint->sent_at
                    ?? $touchpoint->created_at
                    ?? now())->diffForHumans(),
                'color' => match ($touchpoint->status) {
                    CustomerTouchpoint::STATUS_FAILED => 'danger',
                    CustomerTouchpoint::STATUS_SKIPPED => 'warning',
                    default => 'info',
                },
                'thread' => (string) data_get($touchpoint->metadata, 'provider_thread_key', data_get($touchpoint->metadata, 'conversation_key')),
            ]))
            ->take($limit)
            ->values()
            ->all();

        $openReplies = $notes->whereIn('status', ['open', 'in_progress'])->count();
        $pendingCustomer = $notes->where('status', 'pending_customer')->count();
        $lastCustomerMessage = $notes->sortByDesc(fn (CustomerNote $threadNote) => $threadNote->last_customer_message_at ?? $threadNote->created_at)->first();
        $lastTeamMessage = $touchpoints->sortByDesc(fn (CustomerTouchpoint $touchpoint) => $touchpoint->sent_at ?? $touchpoint->created_at)->first();

        return [
            'summary' => [
                'open_replies' => $openReplies,
                'pending_customer' => $pendingCustomer,
                'internal_notes' => 0,
                'last_customer_message' => $lastCustomerMessage?->last_customer_message_at?->diffForHumans() ?? $lastCustomerMessage?->created_at?->diffForHumans(),
                'last_team_message' => $lastTeamMessage?->sent_at?->diffForHumans() ?? $lastTeamMessage?->created_at?->diffForHumans(),
            ],
            'items' => $items,
            'internal_notes' => [],
        ];
    }
}
