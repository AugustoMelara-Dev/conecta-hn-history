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

class CustomerConversationOpsManager
{
    public function __construct(
        private readonly CustomerSegmentPlaybook $segmentPlaybook,
        private readonly CustomerTaskManager $customerTaskManager,
        private readonly CustomerConversationManager $conversationManager,
        private readonly CustomerJourneyManager $journeyManager,
    ) {}

    public function recordInboundReply(
        CustomerTouchpoint $touchpoint,
        string $body,
        Carbon $occurredAt,
        string $channel,
        array $metadata = [],
    ): CustomerNote {
        $profile = $touchpoint->customerProfile()->withoutGlobalScopes()->with(['assignedUser', 'location', 'tenant'])->first();
        $existingNote = $this->latestConversation($touchpoint, $channel);
        $slaHours = $profile instanceof CustomerProfile
            ? max(1, (int) ($this->segmentPlaybook->forProfile($profile)['response_sla_hours'] ?? 24))
            : 24;
        $message = trim($body) !== '' ? trim($body) : 'Respuesta recibida del cliente.';
        $priority = $channel === 'whatsapp' ? 'high' : 'normal';
        $conversationKey = (string) ($existingNote?->conversation_key ?: data_get($touchpoint->metadata, 'conversation_key') ?: Str::uuid()->toString());
        $assignedUserId = $existingNote?->assigned_user_id ?? $profile?->assigned_user_id;
        $locationId = $existingNote?->location_id ?? $profile?->location_id;
        $conversation = $profile instanceof CustomerProfile
            ? $this->conversationManager->ensure($profile, $channel, $conversationKey, [
                'owner_user_id' => $assignedUserId,
                'location_id' => $locationId,
                'status' => 'open',
                'priority' => $priority,
                'last_customer_message_at' => $occurredAt,
                'next_action_at' => $occurredAt->copy()->addHours($slaHours),
                'response_due_at' => $occurredAt->copy()->addHours($slaHours),
                'closed_at' => null,
            ])
            : null;

        if ($existingNote instanceof CustomerNote) {
            $existingNote->forceFill([
                'customer_conversation_id' => $conversation?->getKey(),
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
                'reply_count' => max(1, (int) $existingNote->reply_count) + 1,
                'next_action_at' => $occurredAt->copy()->addHours($slaHours),
                'metadata' => array_merge((array) $existingNote->metadata, [
                    'provider' => $metadata['provider'] ?? null,
                    'channel' => $channel,
                    'source_touchpoint_id' => $metadata['source_touchpoint_id'] ?? null,
                    'last_message_preview' => $message,
                    'last_customer_message_at' => $occurredAt->toIso8601String(),
                ]),
            ])->save();

            $note = $existingNote->fresh();
        } else {
            $note = CustomerNote::query()
                ->withoutGlobalScopes()
                ->create([
                    'tenant_id' => (string) $touchpoint->tenant_id,
                    'customer_profile_id' => (int) $touchpoint->customer_profile_id,
                    'customer_conversation_id' => $conversation?->getKey(),
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

        if ($conversation instanceof CustomerConversation) {
            $touchpoint->forceFill([
                'customer_conversation_id' => $conversation->getKey(),
            ])->save();

            $this->conversationManager->appendMessage(
                $conversation,
                'customer',
                'inbound_message',
                $message,
                $channel,
                null,
                $touchpoint,
                $note,
                $occurredAt,
                'received',
                [
                    'provider' => $metadata['provider'] ?? null,
                    'source_touchpoint_id' => $metadata['source_touchpoint_id'] ?? null,
                ],
            );

            $this->journeyManager->sync($conversation, $profile);
        }

        $this->customerTaskManager->syncFromNote($note);

        return $note->fresh();
    }

    public function markOutboundResponse(CustomerProfile $profile, CustomerTouchpoint $touchpoint, ?string $actorUserId = null): ?CustomerNote
    {
        $note = CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $profile->tenant_id)
            ->where('customer_profile_id', (int) $profile->getKey())
            ->where('source_channel', $touchpoint->channel)
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByDesc('last_customer_message_at')
            ->orderByDesc('created_at')
            ->first();

        $respondedAt = $touchpoint->sent_at ?? now();
        $conversationKey = (string) data_get($touchpoint->metadata, 'conversation_key', $note?->conversation_key ?: Str::uuid()->toString());
        $conversation = $this->conversationManager->ensure($profile, (string) $touchpoint->channel, $conversationKey, [
            'owner_user_id' => $actorUserId ?? $note?->assigned_user_id ?? $profile->assigned_user_id,
            'location_id' => $note?->location_id ?? $profile->location_id,
            'status' => 'waiting_customer',
            'priority' => $note?->priority ?? 'normal',
            'first_response_at' => $note?->first_response_at ?? $respondedAt,
            'last_team_message_at' => $respondedAt,
            'next_action_at' => $respondedAt->copy()->addHours($this->followUpGraceHours()),
            'response_due_at' => $note?->response_due_at,
            'closed_at' => null,
        ]);

        $touchpoint->forceFill([
            'customer_conversation_id' => $conversation->getKey(),
        ])->save();

        if ($note instanceof CustomerNote) {
            $note->forceFill([
                'customer_conversation_id' => $conversation->getKey(),
                'status' => 'pending_customer',
                'requires_follow_up' => false,
                'handled_by_user_id' => $actorUserId ?? $note->handled_by_user_id,
                'assigned_user_id' => $actorUserId ?? $note->assigned_user_id ?? $profile->assigned_user_id,
                'location_id' => $note->location_id ?? $profile->location_id,
                'handled_at' => $respondedAt,
                'first_response_at' => $note->first_response_at ?? $respondedAt,
                'last_team_message_at' => $respondedAt,
                'next_action_at' => $respondedAt->copy()->addHours($this->followUpGraceHours()),
                'metadata' => array_merge((array) $note->metadata, [
                    'last_outbound_touchpoint_id' => (int) $touchpoint->getKey(),
                    'last_outbound_at' => $respondedAt->toIso8601String(),
                ]),
            ])->save();

            $note = $note->fresh();
            $this->customerTaskManager->syncFromNote($note);
        }

        $this->conversationManager->appendMessage(
            $conversation,
            'team',
            'outbound_message',
            (string) ($touchpoint->message ?? $touchpoint->subject ?? 'Seguimiento enviado.'),
            (string) $touchpoint->channel,
            $actorUserId,
            $touchpoint,
            $note,
            $respondedAt,
            'sent',
            [
                'template_key' => $touchpoint->template_key,
                'automation_key' => $touchpoint->automation_key,
            ],
        );

        $this->journeyManager->sync($conversation, $profile);

        return $note;
    }

    public function take(CustomerNote $note, ?string $userId = null): CustomerNote
    {
        $note->forceFill([
            'status' => 'in_progress',
            'handled_by_user_id' => $userId ?? $note->handled_by_user_id,
            'assigned_user_id' => $userId ?? $note->assigned_user_id,
        ])->save();

        $freshNote = $note->fresh();
        $this->syncConversationFromNote($freshNote);
        $this->customerTaskManager->syncFromNote($freshNote);

        return $freshNote;
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

        $freshNote = $note->fresh();
        $this->syncConversationFromNote($freshNote);
        $this->customerTaskManager->syncFromNote($freshNote);

        return $freshNote;
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

        $freshNote = $note->fresh();
        $this->syncConversationFromNote($freshNote);
        $this->customerTaskManager->syncFromNote($freshNote);

        return $freshNote;
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

        $freshNote = $note->fresh();
        $this->syncConversationFromNote($freshNote);
        $this->customerTaskManager->syncFromNote($freshNote);

        return $freshNote;
    }

    public function addInternalNote(CustomerNote $note, string $body, ?string $actorUserId = null): ?CustomerConversationMessage
    {
        $message = trim($body);

        if ($message === '') {
            return null;
        }

        $profile = $note->customerProfile()->withoutGlobalScopes()->with('tenant')->first();

        if (! $profile instanceof CustomerProfile) {
            return null;
        }

        $conversation = $this->syncConversationFromNote($note, $profile);

        if (! $conversation instanceof CustomerConversation) {
            return null;
        }

        $entry = $this->conversationManager->appendMessage(
            $conversation,
            'internal',
            'internal_note',
            $message,
            $note->source_channel,
            $actorUserId,
            null,
            $note,
            now(),
            null,
            [
                'note_type' => $note->note_type,
            ],
        );

        $this->journeyManager->sync($conversation, $profile);

        return $entry;
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

    private function syncConversationFromNote(CustomerNote $note, ?CustomerProfile $profile = null): ?CustomerConversation
    {
        $profile ??= $note->customerProfile()->withoutGlobalScopes()->with('tenant')->first();

        if (! $profile instanceof CustomerProfile) {
            return null;
        }

        $conversation = $this->conversationManager->ensure($profile, (string) ($note->source_channel ?? 'crm'), $note->conversation_key, [
            'owner_user_id' => $note->assigned_user_id ?? $profile->assigned_user_id,
            'location_id' => $note->location_id ?? $profile->location_id,
            'status' => $this->conversationStatusFromNote($note),
            'priority' => $note->priority ?: 'normal',
            'first_response_at' => $note->first_response_at,
            'last_customer_message_at' => $note->last_customer_message_at,
            'last_team_message_at' => $note->last_team_message_at,
            'next_action_at' => $note->next_action_at,
            'response_due_at' => $note->response_due_at,
            'closed_at' => $note->status === 'resolved' ? ($note->handled_at ?? now()) : null,
        ]);

        if ((int) $note->customer_conversation_id !== (int) $conversation->getKey()) {
            $note->forceFill([
                'customer_conversation_id' => $conversation->getKey(),
            ])->save();
        }

        $this->journeyManager->sync($conversation, $profile);

        return $conversation;
    }

    private function conversationStatusFromNote(CustomerNote $note): string
    {
        return match ($note->status) {
            'resolved' => 'closed',
            'pending_customer' => 'waiting_customer',
            default => (string) $note->status,
        };
    }
}
