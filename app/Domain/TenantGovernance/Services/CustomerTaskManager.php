<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTask;

class CustomerTaskManager
{
    public function syncFromNote(CustomerNote $note): ?CustomerTask
    {
        if ($note->customer_profile_id === null) {
            return null;
        }

        $task = CustomerTask::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'tenant_id' => (string) $note->tenant_id,
                'customer_note_id' => (int) $note->getKey(),
            ]);

        $task->forceFill([
            'customer_profile_id' => (int) $note->customer_profile_id,
            'assigned_user_id' => $note->assigned_user_id,
            'location_id' => $note->location_id,
            'task_type' => $note->note_type === 'inbound_reply' ? 'reply_follow_up' : ($note->note_type ?: 'follow_up'),
            'title' => $this->resolveTitle($note),
            'body' => $note->body,
            'status' => $this->resolveStatus($note),
            'priority' => $note->priority ?: 'normal',
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

        $task->save();

        return $task->fresh();
    }

    public function complete(CustomerTask $task, ?string $actorUserId = null): CustomerTask
    {
        $task->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
            'assigned_user_id' => $actorUserId ?? $task->assigned_user_id,
        ])->save();

        return $task->fresh();
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
