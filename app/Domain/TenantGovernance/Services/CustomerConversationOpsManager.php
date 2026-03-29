<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerNoteRepositoryInterface;
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
        private readonly CustomerConversationNoteSynchronizer $conversationNoteSynchronizer,
        private readonly CustomerConversationRouting $routing,
        private readonly CustomerNoteRepositoryInterface $customerNotes,
        private readonly CustomerInboundConversationRecorder $inboundConversationRecorder,
    ) {}

    public function recordInboundReply(
        CustomerTouchpoint $touchpoint,
        string $body,
        Carbon $occurredAt,
        string $channel,
        array $metadata = [],
    ): CustomerNote {
        return $this->inboundConversationRecorder->record($touchpoint, $body, $occurredAt, $channel, $metadata);
    }

    public function markOutboundResponse(CustomerProfile $profile, CustomerTouchpoint $touchpoint, ?string $actorUserId = null): ?CustomerNote
    {
        $routing = $this->routing->resolve($profile, (string) $touchpoint->channel);
        $note = $this->customerNotes->latestOpenConversationNote(
            (string) $profile->tenant_id,
            (int) $profile->getKey(),
            (string) $touchpoint->channel,
        );

        $respondedAt = $touchpoint->sent_at ?? now();
        $conversationKey = (string) data_get($touchpoint->metadata, 'conversation_key', $note?->conversation_key ?: Str::uuid()->toString());
        $conversation = $this->conversationManager->ensure($profile, (string) $touchpoint->channel, $conversationKey, [
            'owner_user_id' => $actorUserId ?? $note?->assigned_user_id ?? $profile->assigned_user_id,
            'location_id' => $note?->location_id ?? $profile->location_id,
            'assignment_group' => data_get($note?->metadata, 'assignment_group', $routing['assignment_group']),
            'status' => 'waiting_customer',
            'priority' => $note?->priority ?? 'normal',
            'response_sla_minutes' => (int) $routing['response_sla_minutes'],
            'first_response_at' => $note?->first_response_at ?? $respondedAt,
            'last_team_message_at' => $respondedAt,
            'next_action_at' => $respondedAt->copy()->addHours($this->followUpGraceHours()),
            'response_due_at' => $note?->response_due_at,
            'escalation_due_at' => $respondedAt->copy()->addMinutes((int) max($routing['escalation_minutes'], $this->followUpGraceHours() * 60)),
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
                    'assignment_group' => data_get($note->metadata, 'assignment_group', $routing['assignment_group']),
                    'assignment_group_label' => data_get($note->metadata, 'assignment_group_label', $routing['assignment_group_label']),
                    'last_outbound_touchpoint_id' => (int) $touchpoint->getKey(),
                    'last_outbound_at' => $respondedAt->toIso8601String(),
                ]),
            ]);

            $note = $this->customerNotes->save($note);
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
                'provider_message_id' => $touchpoint->provider_message_id,
                'provider_thread_key' => data_get($touchpoint->metadata, 'provider_thread_key', data_get($touchpoint->metadata, 'conversation_key')),
                'provider_reply_to' => data_get($touchpoint->metadata, 'provider_reply_to'),
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
        ]);

        $freshNote = $this->customerNotes->save($note);
        $this->conversationNoteSynchronizer->syncFromNote($freshNote);
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
        ]);

        $freshNote = $this->customerNotes->save($note);
        $this->conversationNoteSynchronizer->syncFromNote($freshNote);
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
        ]);

        $freshNote = $this->customerNotes->save($note);
        $this->conversationNoteSynchronizer->syncFromNote($freshNote);
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
        ]);

        $freshNote = $this->customerNotes->save($note);
        $this->conversationNoteSynchronizer->syncFromNote($freshNote);
        $this->customerTaskManager->syncFromNote($freshNote);

        return $freshNote;
    }

    public function routeGroup(CustomerNote $note, ?string $group = null, ?string $actorUserId = null): CustomerNote
    {
        $note->forceFill([
            'status' => $note->status === 'resolved' ? 'open' : $note->status,
            'requires_follow_up' => true,
            'metadata' => array_merge((array) $note->metadata, [
                'assignment_group' => $group,
                'assignment_group_label' => $group !== null ? (string) config("customer-operations.groups.{$group}.label", ucfirst($group)) : 'Sin equipo',
                'last_group_routed_at' => now()->toIso8601String(),
                'last_group_routed_by' => $actorUserId,
            ]),
        ]);

        $freshNote = $this->customerNotes->save($note);
        $this->conversationNoteSynchronizer->syncFromNote($freshNote);
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

        $conversation = $this->conversationNoteSynchronizer->syncFromNote($note, $profile);

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

    private function followUpGraceHours(): int
    {
        return max(1, (int) config('customer-loyalty.conversation.follow_up_grace_hours', 48));
    }

}
