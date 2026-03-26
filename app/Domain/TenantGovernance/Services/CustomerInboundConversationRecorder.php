<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerNoteRepositoryInterface;
use App\Models\CustomerConversation;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;

final class CustomerInboundConversationRecorder
{
    public function __construct(
        private readonly CustomerInboundConversationContextResolver $contextResolver,
        private readonly CustomerConversationManager $conversationManager,
        private readonly CustomerJourneyManager $journeyManager,
        private readonly CustomerTaskManager $customerTaskManager,
        private readonly CustomerNoteRepositoryInterface $customerNotes,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        CustomerTouchpoint $touchpoint,
        string $body,
        Carbon $occurredAt,
        string $channel,
        array $metadata = [],
    ): CustomerNote {
        $context = $this->contextResolver->resolve($touchpoint, $body, $occurredAt, $channel);
        /** @var CustomerProfile|null $profile */
        $profile = $context['profile'];
        /** @var CustomerNote|null $existingNote */
        $existingNote = $context['existingNote'];
        $routing = $context['routing'];
        $message = $context['message'];
        $priority = $context['priority'];
        $conversationKey = $context['conversationKey'];
        $assignedUserId = $context['assignedUserId'];
        $locationId = $context['locationId'];
        $responseDueAt = $context['responseDueAt'];
        $escalationDueAt = $context['escalationDueAt'];
        $conversation = $profile instanceof CustomerProfile
            ? $this->conversationManager->ensure($profile, $channel, $conversationKey, [
                'owner_user_id' => $assignedUserId,
                'location_id' => $locationId,
                'assignment_group' => $routing['assignment_group'],
                'status' => 'open',
                'priority' => $priority,
                'response_sla_minutes' => (int) $routing['response_sla_minutes'],
                'last_customer_message_at' => $occurredAt,
                'next_action_at' => $responseDueAt,
                'response_due_at' => $responseDueAt,
                'escalation_due_at' => $escalationDueAt,
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
                'response_due_at' => $responseDueAt,
                'last_customer_message_at' => $occurredAt,
                'reply_count' => max(1, (int) $existingNote->reply_count) + 1,
                'next_action_at' => $responseDueAt,
                'metadata' => array_merge((array) $existingNote->metadata, $this->metadataPayload(
                    $touchpoint,
                    $metadata,
                    $channel,
                    $routing,
                    $message,
                    $occurredAt,
                )),
            ]);

            $note = $this->customerNotes->save($existingNote);
        } else {
            $note = $this->customerNotes->create([
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
                'response_due_at' => $responseDueAt,
                'last_customer_message_at' => $occurredAt,
                'reply_count' => 1,
                'metadata' => $this->metadataPayload(
                    $touchpoint,
                    $metadata,
                    $channel,
                    $routing,
                    $message,
                    $occurredAt,
                ),
                'pinned_at' => null,
                'next_action_at' => $responseDueAt,
            ]);
        }

        if ($conversation instanceof CustomerConversation && $profile instanceof CustomerProfile) {
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
                    'provider_message_id' => $metadata['provider_message_id'] ?? null,
                    'provider_thread_key' => $metadata['provider_thread_key'] ?? data_get($touchpoint->metadata, 'provider_thread_key'),
                    'provider_reply_to' => $metadata['provider_reply_to'] ?? $touchpoint->provider_message_id,
                ],
            );

            $this->journeyManager->sync($conversation, $profile);
        }

        $this->customerTaskManager->syncFromNote($note);

        return $note->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataPayload(
        CustomerTouchpoint $touchpoint,
        array $metadata,
        string $channel,
        array $routing,
        string $message,
        Carbon $occurredAt,
    ): array {
        return [
            'provider' => $metadata['provider'] ?? null,
            'channel' => $channel,
            'assignment_group' => $routing['assignment_group'],
            'assignment_group_label' => $routing['assignment_group_label'],
            'response_sla_minutes' => (int) $routing['response_sla_minutes'],
            'escalation_minutes' => (int) $routing['escalation_minutes'],
            'source_touchpoint_id' => $metadata['source_touchpoint_id'] ?? null,
            'last_message_preview' => $message,
            'last_customer_message_at' => $occurredAt->toIso8601String(),
            'provider_message_id' => $metadata['provider_message_id'] ?? null,
            'provider_thread_key' => $metadata['provider_thread_key'] ?? data_get($touchpoint->metadata, 'provider_thread_key'),
            'provider_reply_to' => $metadata['provider_reply_to'] ?? $touchpoint->provider_message_id,
        ];
    }
}
