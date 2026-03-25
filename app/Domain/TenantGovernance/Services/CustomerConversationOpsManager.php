<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CustomerConversationOpsManager
{
    public function __construct(
        private readonly CustomerSegmentPlaybook $segmentPlaybook,
    ) {}

    public function recordInboundReply(
        CustomerTouchpoint $touchpoint,
        string $body,
        Carbon $occurredAt,
        string $channel,
        array $metadata = [],
    ): CustomerNote {
        $profile = $touchpoint->customerProfile()->withoutGlobalScopes()->with(['assignedUser', 'location'])->first();
        $conversation = $this->latestConversation($touchpoint, $channel);
        $slaHours = $profile instanceof CustomerProfile
            ? max(1, (int) ($this->segmentPlaybook->forProfile($profile)['response_sla_hours'] ?? 24))
            : 24;
        $message = trim($body) !== '' ? trim($body) : 'Respuesta recibida del cliente.';
        $priority = $channel === 'whatsapp' ? 'high' : 'normal';
        $conversationKey = (string) ($conversation?->conversation_key ?: Str::uuid()->toString());
        $assignedUserId = $conversation?->assigned_user_id ?? $profile?->assigned_user_id;
        $locationId = $conversation?->location_id ?? $profile?->location_id;

        if ($conversation instanceof CustomerNote) {
            $conversation->forceFill([
                'note_type' => 'inbound_reply',
                'status' => 'open',
                'priority' => $priority,
                'source_channel' => $channel,
                'requires_follow_up' => true,
                'body' => $message,
                'conversation_key' => $conversationKey,
                'assigned_user_id' => $assignedUserId,
                'location_id' => $locationId,
                'response_due_at' => $occurredAt->copy()->addHours($slaHours),
                'last_customer_message_at' => $occurredAt,
                'reply_count' => max(1, (int) $conversation->reply_count) + 1,
                'next_action_at' => $occurredAt->copy()->addHours($slaHours),
                'metadata' => array_merge((array) $conversation->metadata, [
                    'provider' => $metadata['provider'] ?? null,
                    'channel' => $channel,
                    'source_touchpoint_id' => $metadata['source_touchpoint_id'] ?? null,
                    'last_message_preview' => $message,
                    'last_customer_message_at' => $occurredAt->toIso8601String(),
                ]),
            ])->save();

            return $conversation->fresh();
        }

        return CustomerNote::query()
            ->withoutGlobalScopes()
            ->create([
                'tenant_id' => (string) $touchpoint->tenant_id,
                'customer_profile_id' => (int) $touchpoint->customer_profile_id,
                'assigned_user_id' => $assignedUserId,
                'location_id' => $locationId,
                'note_type' => 'inbound_reply',
                'status' => 'open',
                'priority' => $priority,
                'source_channel' => $channel,
                'requires_follow_up' => true,
                'body' => $message,
                'conversation_key' => $conversationKey,
                'response_due_at' => $occurredAt->copy()->addHours($slaHours),
                'last_customer_message_at' => $occurredAt,
                'reply_count' => 1,
                'metadata' => [
                    'provider' => $metadata['provider'] ?? null,
                    'channel' => $channel,
                    'source_touchpoint_id' => $metadata['source_touchpoint_id'] ?? null,
                    'last_message_preview' => $message,
                    'last_customer_message_at' => $occurredAt->toIso8601String(),
                ],
                'pinned_at' => null,
                'next_action_at' => $occurredAt->copy()->addHours($slaHours),
            ]);
    }

    public function markOutboundResponse(CustomerProfile $profile, CustomerTouchpoint $touchpoint, ?string $actorUserId = null): ?CustomerNote
    {
        $conversation = CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $profile->tenant_id)
            ->where('customer_profile_id', (int) $profile->getKey())
            ->where('source_channel', $touchpoint->channel)
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByDesc('last_customer_message_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $conversation instanceof CustomerNote) {
            return null;
        }

        $respondedAt = $touchpoint->sent_at ?? now();

        $conversation->forceFill([
            'status' => 'pending_customer',
            'requires_follow_up' => false,
            'handled_by_user_id' => $actorUserId ?? $conversation->handled_by_user_id,
            'assigned_user_id' => $actorUserId ?? $conversation->assigned_user_id ?? $profile->assigned_user_id,
            'location_id' => $conversation->location_id ?? $profile->location_id,
            'handled_at' => $respondedAt,
            'first_response_at' => $conversation->first_response_at ?? $respondedAt,
            'last_team_message_at' => $respondedAt,
            'next_action_at' => $respondedAt->copy()->addHours($this->followUpGraceHours()),
            'metadata' => array_merge((array) $conversation->metadata, [
                'last_outbound_touchpoint_id' => (int) $touchpoint->getKey(),
                'last_outbound_at' => $respondedAt->toIso8601String(),
            ]),
        ])->save();

        return $conversation->fresh();
    }

    public function take(CustomerNote $note, ?string $userId = null): CustomerNote
    {
        $note->forceFill([
            'status' => 'in_progress',
            'handled_by_user_id' => $userId ?? $note->handled_by_user_id,
            'assigned_user_id' => $userId ?? $note->assigned_user_id,
        ])->save();

        return $note->fresh();
    }

    public function resolve(CustomerNote $note, ?string $userId = null): CustomerNote
    {
        $note->forceFill([
            'status' => 'resolved',
            'handled_by_user_id' => $userId ?? $note->handled_by_user_id,
            'assigned_user_id' => $userId ?? $note->assigned_user_id,
            'handled_at' => now(),
            'requires_follow_up' => false,
            'next_action_at' => null,
        ])->save();

        return $note->fresh();
    }

    public function reassign(CustomerNote $note, ?string $userId = null, ?string $actorUserId = null): CustomerNote
    {
        $reopened = filled($userId) && (string) $note->assigned_user_id !== (string) $userId;

        $note->forceFill([
            'status' => $reopened ? 'open' : $note->status,
            'assigned_user_id' => $userId,
            'handled_by_user_id' => $reopened ? null : $note->handled_by_user_id,
            'requires_follow_up' => true,
            'next_action_at' => $note->next_action_at ?? now()->addHours($this->followUpGraceHours()),
            'metadata' => array_merge((array) $note->metadata, [
                'last_routed_at' => now()->toIso8601String(),
                'last_routed_by' => $actorUserId,
                'last_owner_id' => $userId,
            ]),
        ])->save();

        return $note->fresh();
    }

    public function moveToLocation(CustomerNote $note, ?string $locationId = null, ?string $actorUserId = null): CustomerNote
    {
        $note->forceFill([
            'location_id' => $locationId,
            'metadata' => array_merge((array) $note->metadata, [
                'last_location_routed_at' => now()->toIso8601String(),
                'last_location_routed_by' => $actorUserId,
                'last_location_id' => $locationId,
            ]),
        ])->save();

        return $note->fresh();
    }

    private function latestConversation(CustomerTouchpoint $touchpoint, string $channel): ?CustomerNote
    {
        $window = max(1, (int) config('customer-loyalty.conversation.window_hours', 72));

        return CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $touchpoint->tenant_id)
            ->where('customer_profile_id', (int) $touchpoint->customer_profile_id)
            ->where('source_channel', $channel)
            ->whereIn('status', ['open', 'in_progress', 'pending_customer'])
            ->where('created_at', '>=', now()->subHours($window))
            ->orderByDesc('last_customer_message_at')
            ->orderByDesc('created_at')
            ->first();
    }

    private function followUpGraceHours(): int
    {
        return max(1, (int) config('customer-loyalty.conversation.follow_up_grace_hours', 48));
    }
}
