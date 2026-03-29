<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerTaskRepositoryInterface;
use App\Models\CustomerConversation;
use App\Models\CustomerJourneyRun;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTask;

class CustomerTaskManager
{
    public function __construct(
        private readonly CustomerTaskRepositoryInterface $customerTasks,
    ) {}

    public function syncFromNote(CustomerNote $note): ?CustomerTask
    {
        if ((int) $note->customer_profile_id <= 0) {
            return null;
        }

        /** @var CustomerConversation|null $conversation */
        $conversation = $note->conversation()->withoutGlobalScopes()->first();
        $assignmentGroup = $conversation instanceof CustomerConversation
            ? $conversation->assignment_group
            : data_get($note->metadata, 'assignment_group');
        $responseSlaMinutes = $conversation instanceof CustomerConversation
            ? $conversation->response_sla_minutes
            : null;

        $task = $this->customerTasks->firstOrNewByNote((string) $note->tenant_id, (int) $note->getKey());

        $task->forceFill([
            'customer_profile_id' => (int) $note->customer_profile_id,
            'customer_conversation_id' => $note->customer_conversation_id,
            'assigned_user_id' => $note->assigned_user_id,
            'location_id' => $note->location_id,
            'assignment_group' => $assignmentGroup,
            'task_type' => $note->note_type === 'inbound_reply' ? 'reply_follow_up' : ($note->note_type ?: 'follow_up'),
            'title' => $this->resolveTitle($note),
            'body' => $note->body,
            'status' => $this->resolveStatus($note),
            'priority' => $note->priority ?: 'normal',
            'response_sla_minutes' => $responseSlaMinutes,
            'source_channel' => $note->source_channel,
            'due_at' => $note->next_action_at,
            'sla_due_at' => $note->response_due_at,
            'completed_at' => $note->status === 'resolved' ? ($note->handled_at ?? now()) : null,
            'metadata' => array_merge((array) $task->metadata, [
                'note_type' => $note->note_type,
                'conversation_key' => $note->conversation_key,
                'reply_count' => (int) $note->reply_count,
            ]),
        ]);

        if (! $task->exists) {
            $task->created_by_user_id = $note->created_by_user_id;
        }

        return $this->customerTasks->save($task);
    }

    public function complete(CustomerTask $task, ?string $actorUserId = null): CustomerTask
    {
        $task->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
            'assigned_user_id' => $actorUserId ?? $task->assigned_user_id,
        ]);

        return $this->customerTasks->save($task);
    }

    public function createJourneyRescueTask(CustomerJourneyRun $run, string $reason): ?CustomerTask
    {
        /** @var CustomerConversation|null $conversation */
        $conversation = $run->conversation()->withoutGlobalScopes()->first();
        $profile = $run->customerProfile()->withoutGlobalScopes()->first();

        if (! $profile instanceof CustomerProfile) {
            return null;
        }

        $assignedUserId = $conversation instanceof CustomerConversation
            ? $conversation->owner_user_id
            : $profile->assigned_user_id;
        $locationId = $conversation instanceof CustomerConversation
            ? $conversation->location_id
            : $profile->location_id;
        $assignmentGroup = $conversation instanceof CustomerConversation
            ? $conversation->assignment_group
            : 'retention';
        $responseSlaMinutes = $conversation instanceof CustomerConversation
            ? $conversation->response_sla_minutes
            : 120;
        $sourceChannel = $conversation instanceof CustomerConversation
            ? $conversation->source_channel
            : $profile->preferred_channel;
        $dueAt = $run->step_due_at;

        if ($dueAt === null && $conversation instanceof CustomerConversation) {
            $dueAt = $conversation->next_action_at;
        }

        $dueAt ??= now()->addHours(4);
        $slaDueAt = $conversation instanceof CustomerConversation
            ? ($conversation->response_due_at ?? now()->addHours(2))
            : now()->addHours(2);

        $task = $this->customerTasks->firstOpenJourneyRescueTask((string) $run->tenant_id, (int) $run->customer_conversation_id);

        $task->forceFill([
            'customer_profile_id' => (int) $profile->getKey(),
            'assigned_user_id' => $assignedUserId,
            'location_id' => $locationId,
            'assignment_group' => $assignmentGroup,
            'title' => 'Rescatar secuencia de '.$profile->name,
            'body' => $reason,
            'priority' => 'high',
            'response_sla_minutes' => $responseSlaMinutes,
            'source_channel' => $sourceChannel,
            'due_at' => $dueAt,
            'sla_due_at' => $slaDueAt,
            'metadata' => array_merge((array) $task->metadata, [
                'journey_key' => $run->journey_key,
                'sequence_key' => data_get($run->metadata, 'current_sequence_key'),
                'branch_key' => data_get($run->metadata, 'current_branch_key'),
                'lane' => data_get($run->metadata, 'lane'),
                'rescue_reason' => $reason,
            ]),
        ]);

        if (! $task->exists) {
            $task->created_by_user_id = $conversation instanceof CustomerConversation ? $conversation->owner_user_id : null;
        }

        return $this->customerTasks->save($task);
    }

    private function resolveTitle(CustomerNote $note): string
    {
        $customerName = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->whereKey($note->customer_profile_id)
            ->value('name') ?: 'Cliente';

        return match ($note->note_type) {
            'inbound_reply' => 'Responder a '.$customerName,
            'sale' => 'Seguimiento de venta para '.$customerName,
            'service' => 'Seguimiento de servicio para '.$customerName,
            default => 'Seguimiento de '.$customerName,
        };
    }

    private function resolveStatus(CustomerNote $note): string
    {
        return match ($note->status) {
            'resolved' => 'completed',
            'pending_customer' => 'waiting_customer',
            'in_progress' => 'in_progress',
            default => 'open',
        };
    }
}
