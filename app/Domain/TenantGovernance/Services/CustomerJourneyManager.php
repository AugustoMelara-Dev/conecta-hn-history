<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerConversation;
use App\Models\CustomerJourneyRun;
use App\Models\CustomerProfile;

class CustomerJourneyManager
{
    public function sync(CustomerConversation $conversation, CustomerProfile $profile): CustomerJourneyRun
    {
        /** @var CustomerConversationTemplateManager $templateManager */
        $templateManager = app(CustomerConversationTemplateManager::class);
        $playbook = $templateManager->forConversation($profile);
        $steps = (array) ($playbook['journey'] ?? []);
        $segmentKey = (string) ($playbook['segment_key'] ?? 'new');
        $businessSegmentKey = filled($profile->tenant?->business_segment_key)
            ? (string) $profile->tenant->business_segment_key
            : null;

        $run = CustomerJourneyRun::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'customer_conversation_id' => (int) $conversation->getKey(),
            ]);

        $currentStepIndex = $this->resolveStepIndex($conversation, count($steps));
        $status = $conversation->status === 'closed' ? 'completed' : 'active';

        $run->forceFill([
            'tenant_id' => (string) $conversation->tenant_id,
            'customer_profile_id' => (int) $profile->getKey(),
            'journey_key' => $this->journeyKey($segmentKey, $businessSegmentKey),
            'segment_key' => $segmentKey,
            'business_segment_key' => $businessSegmentKey,
            'status' => $status,
            'current_step_index' => $currentStepIndex,
            'next_action_at' => $conversation->next_action_at,
            'last_transition_at' => now(),
            'completed_at' => $status === 'completed' ? ($conversation->closed_at ?? now()) : null,
            'metadata' => [
                'steps' => $steps,
                'current_step' => $steps[$currentStepIndex] ?? null,
                'conversation_status' => $conversation->status,
            ],
        ])->save();

        return $run->fresh();
    }

    private function resolveStepIndex(CustomerConversation $conversation, int $stepsCount): int
    {
        $lastStepIndex = max(0, $stepsCount - 1);

        return match ($conversation->status) {
            'in_progress' => min(1, $lastStepIndex),
            'waiting_customer' => min(2, $lastStepIndex),
            'closed' => $lastStepIndex,
            default => 0,
        };
    }

    private function journeyKey(string $segmentKey, ?string $businessSegmentKey): string
    {
        return $businessSegmentKey !== null
            ? "{$businessSegmentKey}:{$segmentKey}"
            : $segmentKey;
    }
}
