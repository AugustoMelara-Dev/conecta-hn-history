<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerNoteRepositoryInterface;
use App\Models\CustomerConversation;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;

final class CustomerConversationNoteSynchronizer
{
    public function __construct(
        private readonly CustomerConversationManager $conversationManager,
        private readonly CustomerJourneyManager $journeyManager,
        private readonly CustomerConversationRouting $routing,
        private readonly CustomerNoteRepositoryInterface $customerNotes,
    ) {}

    public function syncFromNote(CustomerNote $note, ?CustomerProfile $profile = null): ?CustomerConversation
    {
        $profile ??= $note->customerProfile()->withoutGlobalScopes()->with('tenant')->first();

        if (! $profile instanceof CustomerProfile) {
            return null;
        }

        $channel = (string) ($note->source_channel ?? 'crm');
        $routing = $this->routing->resolve($profile, $channel);

        $conversation = $this->conversationManager->ensure($profile, $channel, $note->conversation_key, [
            'assignment_group' => data_get($note->metadata, 'assignment_group', $routing['assignment_group']),
            'owner_user_id' => $note->assigned_user_id ?? $profile->assigned_user_id,
            'location_id' => $note->location_id ?? $profile->location_id,
            'status' => $this->conversationStatusFromNote($note),
            'priority' => $note->priority ?: 'normal',
            'response_sla_minutes' => (int) data_get($note->metadata, 'response_sla_minutes', $routing['response_sla_minutes']),
            'first_response_at' => $note->first_response_at,
            'last_customer_message_at' => $note->last_customer_message_at,
            'last_team_message_at' => $note->last_team_message_at,
            'next_action_at' => $note->next_action_at,
            'response_due_at' => $note->response_due_at,
            'escalation_due_at' => $note->response_due_at?->copy()->addMinutes((int) data_get($note->metadata, 'escalation_minutes', $routing['escalation_minutes'])),
            'closed_at' => $note->status === 'resolved' ? ($note->handled_at ?? now()) : null,
        ]);

        if ((int) $note->customer_conversation_id !== (int) $conversation->getKey()) {
            $note->forceFill([
                'customer_conversation_id' => $conversation->getKey(),
            ]);

            $this->customerNotes->save($note);
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
