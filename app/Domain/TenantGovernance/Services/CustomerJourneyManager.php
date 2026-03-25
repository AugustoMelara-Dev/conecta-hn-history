<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerConversation;
use App\Models\CustomerJourneyRun;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;

class CustomerJourneyManager
{
    public function sync(CustomerConversation $conversation, CustomerProfile $profile): CustomerJourneyRun
    {
        /** @var CustomerConversationTemplateManager $templateManager */
        $templateManager = app(CustomerConversationTemplateManager::class);
        $steps = $templateManager->journeyDefinition($profile);
        $segmentKey = (string) ($profile->manual_segment ?: $profile->customer_segment ?: 'new');
        $businessSegmentKey = filled($profile->tenant?->business_segment_key)
            ? (string) $profile->tenant->business_segment_key
            : null;
        $run = CustomerJourneyRun::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'customer_conversation_id' => (int) $conversation->getKey(),
            ]);
        $resolved = $this->resolveStepState($conversation, $steps, $segmentKey, $run->exists ? (int) $run->current_step_index : null);
        $currentStep = $resolved['current_step'];
        $nextStep = $resolved['next_step'];
        $stepDueAt = $resolved['step_due_at'];
        $status = $conversation->status === 'closed' ? 'completed' : 'active';
        $sequenceState = $this->resolveSequenceState($conversation, $stepDueAt, $status);
        $dueState = $this->resolveDueState($conversation, $stepDueAt, $status);
        $currentStepIndex = (int) ($resolved['current_step_index'] ?? 0);
        $currentStepKey = (string) ($currentStep['key'] ?? '');
        $stepChanged = $currentStepKey !== (string) ($run->current_step_key ?? '');
        $currentStepExecutedAt = $stepChanged ? null : $run->current_step_executed_at;

        $run->forceFill([
            'tenant_id' => (string) $conversation->tenant_id,
            'customer_profile_id' => (int) $profile->getKey(),
            'journey_key' => $this->journeyKey($segmentKey, $businessSegmentKey),
            'segment_key' => $segmentKey,
            'business_segment_key' => $businessSegmentKey,
            'status' => $status,
            'current_step_key' => $currentStep['key'] ?? null,
            'next_template_key' => $currentStep['template_key'] ?? $nextStep['template_key'] ?? null,
            'sequence_state' => $sequenceState,
            'due_state' => $dueState,
            'current_step_index' => $currentStepIndex,
            'next_action_at' => $conversation->next_action_at,
            'step_due_at' => $stepDueAt,
            'current_step_executed_at' => $currentStepExecutedAt,
            'auto_step_count' => (int) ($run->auto_step_count ?? 0),
            'last_auto_touchpoint_id' => $run->last_auto_touchpoint_id,
            'last_transition_at' => now(),
            'completed_at' => $status === 'completed' ? ($conversation->closed_at ?? now()) : null,
            'metadata' => [
                'steps' => $steps,
                'current_step' => $currentStep,
                'next_step' => $nextStep,
                'conversation_status' => $conversation->status,
                'recommended_template_key' => $currentStep['template_key'] ?? $nextStep['template_key'] ?? null,
                'lane' => $currentStep['lane'] ?? null,
                'sequence_state_label' => $this->sequenceStateLabel($sequenceState),
                'due_state_label' => $this->dueStateLabel($dueState),
                'step_due_at' => $stepDueAt?->toIso8601String(),
                'auto_send' => (bool) ($currentStep['auto_send'] ?? false),
                'current_step_executed_at' => $currentStepExecutedAt?->toIso8601String(),
            ],
        ])->save();

        return $run->fresh();
    }

    public function advanceAfterAutomation(CustomerJourneyRun $run, CustomerTouchpoint $touchpoint): CustomerJourneyRun
    {
        $run = $run->fresh();

        if (! $run instanceof CustomerJourneyRun) {
            return $run;
        }

        $conversation = $run->conversation()->withoutGlobalScopes()->first();

        if (! $conversation instanceof CustomerConversation) {
            return $run;
        }

        $steps = collect((array) data_get($run->metadata, 'steps', []))
            ->map(static fn (mixed $step): array => is_array($step) ? $step : [])
            ->values()
            ->all();

        if ($steps === []) {
            return $run;
        }

        $executedAt = $touchpoint->sent_at ?? now();
        $currentIndex = min(max(0, (int) $run->current_step_index), max(0, count($steps) - 1));
        $nextIndex = min($currentIndex + 1, max(0, count($steps) - 1));
        $hasAdvanced = $nextIndex > $currentIndex;
        $currentStep = $steps[$nextIndex] ?? null;
        $nextStep = $nextIndex < count($steps) - 1 ? ($steps[$nextIndex + 1] ?? null) : null;
        $stepDueAt = $this->stepDueAtFromExecution($executedAt, $currentStep, $conversation);

        $conversation->forceFill([
            'status' => 'waiting_customer',
            'last_team_message_at' => $executedAt,
            'next_action_at' => $stepDueAt,
            'escalation_due_at' => $stepDueAt,
        ])->save();

        $currentStepExecutedAt = $hasAdvanced ? null : $executedAt;
        $sequenceState = 'waiting_customer';
        $dueState = $stepDueAt instanceof Carbon && $stepDueAt->isPast() ? 'breached' : 'scheduled';

        $run->forceFill([
            'current_step_index' => $nextIndex,
            'current_step_key' => $currentStep['key'] ?? null,
            'next_template_key' => $currentStep['template_key'] ?? $nextStep['template_key'] ?? null,
            'sequence_state' => $sequenceState,
            'due_state' => $dueState,
            'step_due_at' => $stepDueAt,
            'current_step_executed_at' => $currentStepExecutedAt,
            'auto_step_count' => ((int) $run->auto_step_count) + 1,
            'last_auto_touchpoint_id' => (int) $touchpoint->getKey(),
            'last_transition_at' => now(),
            'metadata' => array_merge((array) $run->metadata, [
                'current_step' => $currentStep,
                'next_step' => $nextStep,
                'recommended_template_key' => $currentStep['template_key'] ?? $nextStep['template_key'] ?? null,
                'lane' => $currentStep['lane'] ?? data_get($run->metadata, 'lane'),
                'sequence_state_label' => $this->sequenceStateLabel($sequenceState),
                'due_state_label' => $this->dueStateLabel($dueState),
                'step_due_at' => $stepDueAt?->toIso8601String(),
                'auto_send' => (bool) ($currentStep['auto_send'] ?? false),
                'current_step_executed_at' => $currentStepExecutedAt?->toIso8601String(),
                'last_auto_touchpoint_id' => (int) $touchpoint->getKey(),
            ]),
        ])->save();

        return $run->fresh();
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return array{
     *     current_step_index:int,
     *     current_step:?array<string, mixed>,
     *     next_step:?array<string, mixed>,
     *     step_due_at:?Carbon
     * }
     */
    private function resolveStepState(CustomerConversation $conversation, array $steps, string $segmentKey, ?int $persistedStepIndex = null): array
    {
        $stepsCount = count($steps);

        if ($stepsCount === 0) {
            return [
                'current_step_index' => 0,
                'current_step' => null,
                'next_step' => null,
                'step_due_at' => null,
            ];
        }

        $lastStepIndex = max(0, $stepsCount - 1);

        if ($conversation->status === 'closed') {
            return [
                'current_step_index' => $lastStepIndex,
                'current_step' => $steps[$lastStepIndex] ?? null,
                'next_step' => null,
                'step_due_at' => $conversation->closed_at,
            ];
        }

        $currentStepIndex = 0;

        if ($segmentKey === 'reactivation') {
            if ($conversation->status === 'waiting_customer') {
                $currentStepIndex = min(1, $lastStepIndex);
            } elseif ($conversation->reply_count >= 2 || $conversation->status === 'in_progress') {
                $currentStepIndex = min(2, $lastStepIndex);
            }
        } elseif ($conversation->status === 'waiting_customer') {
            $currentStepIndex = min(1, $lastStepIndex);
        } elseif ($conversation->reply_count >= 2 || $conversation->status === 'in_progress') {
            $currentStepIndex = min(2, $lastStepIndex);
        }

        if ($persistedStepIndex !== null && $conversation->status !== 'closed') {
            $currentStepIndex = min(max($currentStepIndex, $persistedStepIndex), $lastStepIndex);
        }

        $currentStep = $steps[$currentStepIndex] ?? null;
        $nextStep = $currentStepIndex < $lastStepIndex ? ($steps[$currentStepIndex + 1] ?? null) : null;

        return [
            'current_step_index' => $currentStepIndex,
            'current_step' => $currentStep,
            'next_step' => $nextStep,
            'step_due_at' => $this->resolveStepDueAt($conversation, $currentStep),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $step
     */
    private function resolveStepDueAt(CustomerConversation $conversation, ?array $step): ?Carbon
    {
        if ($step === null) {
            return $conversation->next_action_at ?? $conversation->response_due_at;
        }

        $waitHours = max(0, (int) ($step['wait_hours'] ?? 0));

        if ($conversation->status === 'closed') {
            return $conversation->closed_at;
        }

        if ($conversation->status === 'waiting_customer') {
            $base = $conversation->last_team_message_at ?? $conversation->first_response_at ?? $conversation->updated_at;

            if ($waitHours === 0) {
                return $conversation->next_action_at ?? ($base instanceof Carbon ? $base->copy() : null);
            }

            return $base instanceof Carbon ? $base->copy()->addHours($waitHours) : $conversation->next_action_at;
        }

        $base = $conversation->last_customer_message_at ?? $conversation->opened_at ?? $conversation->updated_at;

        if ($waitHours === 0) {
            return $conversation->response_due_at
                ?? $conversation->next_action_at
                ?? ($base instanceof Carbon ? $base->copy() : null);
        }

        return $base instanceof Carbon ? $base->copy()->addHours($waitHours) : $conversation->next_action_at;
    }

    /**
     * @param  array<string, mixed>|null  $step
     */
    private function stepDueAtFromExecution(Carbon $executedAt, ?array $step, CustomerConversation $conversation): ?Carbon
    {
        if ($step === null) {
            return $conversation->next_action_at ?? $executedAt->copy()->addHours(24);
        }

        $waitHours = max(0, (int) ($step['wait_hours'] ?? 0));

        if ($waitHours === 0) {
            return $conversation->next_action_at ?? $executedAt->copy()->addHours(24);
        }

        return $executedAt->copy()->addHours($waitHours);
    }

    private function resolveSequenceState(CustomerConversation $conversation, ?Carbon $stepDueAt, string $status): string
    {
        if ($status === 'completed') {
            return 'completed';
        }

        if ($conversation->status === 'waiting_customer') {
            return $stepDueAt instanceof Carbon && $stepDueAt->isPast()
                ? 'stalled'
                : 'waiting_customer';
        }

        return $stepDueAt instanceof Carbon && $stepDueAt->isPast()
            ? 'stalled'
            : 'active';
    }

    private function resolveDueState(CustomerConversation $conversation, ?Carbon $stepDueAt, string $status): string
    {
        if ($status === 'completed') {
            return 'completed';
        }

        $deadline = $stepDueAt ?? $conversation->response_due_at;

        if (! $deadline instanceof Carbon) {
            return 'on_track';
        }

        return $deadline->isPast() ? 'breached' : 'scheduled';
    }

    private function journeyKey(string $segmentKey, ?string $businessSegmentKey): string
    {
        return $businessSegmentKey !== null
            ? "{$businessSegmentKey}:{$segmentKey}"
            : $segmentKey;
    }

    private function sequenceStateLabel(string $state): string
    {
        return match ($state) {
            'waiting_customer' => 'Esperando reply',
            'stalled' => 'Requiere rescate',
            'completed' => 'Completado',
            default => 'Listo para mover',
        };
    }

    private function dueStateLabel(string $state): string
    {
        return match ($state) {
            'breached' => 'Paso vencido',
            'scheduled' => 'Programado',
            'completed' => 'Cerrado',
            default => 'En tiempo',
        };
    }
}
