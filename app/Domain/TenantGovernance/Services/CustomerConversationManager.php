<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerConversation;
use App\Models\CustomerConversationMessage;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CustomerConversationManager
{
    public function ensure(
        CustomerProfile $profile,
        string $channel,
        ?string $conversationKey = null,
        array $attributes = [],
    ): CustomerConversation {
        $conversation = null;
        $normalizedKey = filled($conversationKey) ? (string) $conversationKey : null;

        if ($normalizedKey !== null) {
            $conversation = CustomerConversation::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', (string) $profile->tenant_id)
                ->where('customer_profile_id', (int) $profile->getKey())
                ->where('conversation_key', $normalizedKey)
                ->first();
        }

        if (! $conversation instanceof CustomerConversation) {
            $conversation = CustomerConversation::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', (string) $profile->tenant_id)
                ->where('customer_profile_id', (int) $profile->getKey())
                ->where('source_channel', $channel)
                ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
                ->where('updated_at', '>=', now()->subHours($this->windowHours()))
                ->latest('updated_at')
                ->first();
        }

        if (! $conversation instanceof CustomerConversation) {
            $conversation = CustomerConversation::query()
                ->withoutGlobalScopes()
                ->create([
                    'tenant_id' => (string) $profile->tenant_id,
                    'customer_profile_id' => (int) $profile->getKey(),
                    'owner_user_id' => $profile->assigned_user_id,
                    'location_id' => $profile->location_id,
                    'conversation_key' => $normalizedKey ?? Str::uuid()->toString(),
                    'source_channel' => $channel,
                    'status' => 'open',
                    'priority' => 'normal',
                    'opened_at' => now(),
                    'metadata' => [],
                ]);
        }

        $payload = array_filter([
            'owner_user_id' => $attributes['owner_user_id'] ?? $conversation->owner_user_id ?? $profile->assigned_user_id,
            'location_id' => $attributes['location_id'] ?? $conversation->location_id ?? $profile->location_id,
            'status' => $attributes['status'] ?? null,
            'priority' => $attributes['priority'] ?? null,
            'source_channel' => $attributes['source_channel'] ?? null,
            'first_response_at' => $attributes['first_response_at'] ?? null,
            'last_customer_message_at' => $attributes['last_customer_message_at'] ?? null,
            'last_team_message_at' => $attributes['last_team_message_at'] ?? null,
            'next_action_at' => $attributes['next_action_at'] ?? null,
            'response_due_at' => $attributes['response_due_at'] ?? null,
            'closed_at' => $attributes['closed_at'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);

        if ($payload !== []) {
            $conversation->forceFill($payload)->save();
        }

        return $conversation->fresh();
    }

    public function appendMessage(
        CustomerConversation $conversation,
        string $direction,
        string $messageKind,
        ?string $body,
        ?string $channel = null,
        ?string $actorUserId = null,
        ?CustomerTouchpoint $touchpoint = null,
        ?CustomerNote $note = null,
        ?Carbon $occurredAt = null,
        ?string $status = null,
        array $metadata = [],
    ): CustomerConversationMessage {
        $message = CustomerConversationMessage::query()
            ->withoutGlobalScopes()
            ->create([
                'tenant_id' => (string) $conversation->tenant_id,
                'customer_conversation_id' => (int) $conversation->getKey(),
                'customer_profile_id' => (int) $conversation->customer_profile_id,
                'created_by_user_id' => $actorUserId,
                'customer_touchpoint_id' => $touchpoint?->getKey(),
                'customer_note_id' => $note?->getKey(),
                'direction' => $direction,
                'message_kind' => $messageKind,
                'channel' => $channel,
                'body' => $body,
                'status' => $status,
                'occurred_at' => $occurredAt ?? now(),
                'metadata' => $metadata,
            ]);

        $conversation->forceFill([
            'message_count' => $this->messageCount($conversation),
            'internal_note_count' => $this->internalNoteCount($conversation),
            'reply_count' => $this->replyCount($conversation),
        ])->save();

        return $message;
    }

    private function messageCount(CustomerConversation $conversation): int
    {
        return CustomerConversationMessage::query()
            ->withoutGlobalScopes()
            ->where('customer_conversation_id', (int) $conversation->getKey())
            ->whereIn('direction', ['customer', 'team'])
            ->count();
    }

    private function internalNoteCount(CustomerConversation $conversation): int
    {
        return CustomerConversationMessage::query()
            ->withoutGlobalScopes()
            ->where('customer_conversation_id', (int) $conversation->getKey())
            ->where('direction', 'internal')
            ->count();
    }

    private function replyCount(CustomerConversation $conversation): int
    {
        return CustomerConversationMessage::query()
            ->withoutGlobalScopes()
            ->where('customer_conversation_id', (int) $conversation->getKey())
            ->where('direction', 'customer')
            ->count();
    }

    private function windowHours(): int
    {
        return max(24, (int) config('customer-conversations.defaults.window_hours', 168));
    }
}
