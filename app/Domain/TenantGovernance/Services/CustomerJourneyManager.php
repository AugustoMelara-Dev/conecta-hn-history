<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerJourneyRunRepositoryInterface;
use App\Models\CustomerConversation;
use App\Models\CustomerJourneyRun;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use Carbon\Carbon;

class CustomerJourneyManager
{
    public function __construct(
        private readonly CustomerJourneyBranchResolver $branchResolver,
        private readonly CustomerTaskManager $customerTaskManager,
        private readonly CustomerJourneyRunRepositoryInterface $journeyRuns,
    ) {}

    public function sync(CustomerConversation $conversation, CustomerProfile $profile): \App\Models\CustomerJourneyRun
    {
        /** @var CustomerConversationTemplateManager $templateManager */
        $templateManager = app(CustomerConversationTemplateManager::class);
        $steps = $templateManager->journeyDefinition($profile);
        $segmentKey = (string) ($profile->manual_segment ?: $profile->customer_segment ?: 'new');
        $tenant = $profile->tenant;
        $businessSegmentKey = $tenant instanceof Tenant && filled($tenant->business_segment_key)
            ? (string) $tenant->business_segment_key
            : null;
        $run = $this->journeyRuns->firstOrNewByConversation((int) $conversation->getKey());
        $resolved = $this->resolveStepState($conversation, $steps, $segmentKey, $run->exists ? (int) $run->current_step_index : null);
        $journeyKey = $this->journeyKey($segmentKey, $businessSegmentKey);
        $currentStep = $this->branchResolver->resolve(
            $profile,
            $conversation,
            $resolved['current_step'],
            $journeyKey,
            (int) $resolved['current_step_index'],
        );
        $nextStep = $this->branchResolver->resolve(
            $profile,
            $conversation,
            $resolved['next_step'],
            $journeyKey,
            min((int) $resolved['current_step_index'] + 1, max(0, count($steps) - 1)),
        );
        $stepDueAt = $resolved['step_due_at'];
        $status = $conversation->status === 'closed' ? 'completed' : 'active';
        $sequenceState = $this->resolveSequenceState($conversation, $stepDueAt, $status);
        $dueState = $this->resolveDueState($conversation, $stepDueAt, $status);
        $currentStepIndex = (int) $resolved['current_step_index'];
        $currentStepKey = (string) ($currentStep['key'] ?? '');
        $stepChanged = $currentStepKey !== (string) ($run->current_step_key ?? '');
        $currentStepExecutedAt = $stepChanged ? null : $run->current_step_executed_at;

        $run->forceFill([
            'tenant_id' => (string) $conversation->tenant_id,
            'customer_profile_id' => (int) $profile->getKey(),
            'journey_key' => $journeyKey,
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
                'business_signals' => data_get($conversation->metadata, 'business_signals', []),
                'last_business_signal' => data_get($conversation->metadata, 'last_business_signal'),
                'last_business_signal_at' => data_get($conversation->metadata, 'last_business_signal_at'),
                'recommended_template_key' => $currentStep['template_key'] ?? $nextStep['template_key'] ?? null,
                'lane' => $currentStep['lane'] ?? null,
                'current_branch_key' => $currentStep['branch_key'] ?? 'base',
                'current_branch_label' => $currentStep['branch_label'] ?? 'Ruta base',
                'next_branch_key' => $nextStep['branch_key'] ?? null,
                'next_branch_label' => $nextStep['branch_label'] ?? null,
                'current_sequence_key' => $currentStep['sequence_key'] ?? null,
                'current_sequence_label' => $currentStep['sequence_label'] ?? null,
                'next_sequence_key' => $nextStep['sequence_key'] ?? null,
                'next_sequence_label' => $nextStep['sequence_label'] ?? null,
                'sequence_state_label' => $this->sequenceStateLabel($sequenceState),
                'due_state_label' => $this->dueStateLabel($dueState),
                'step_due_at' => $stepDueAt->toIso8601String(),
                'auto_send' => (bool) ($currentStep['auto_send'] ?? false),
                'current_step_executed_at' => $currentStepExecutedAt !== null ? $currentStepExecutedAt->toIso8601String() : null,
            ],
        ]);

        $run = $this->journeyRuns->save($run);

        if ($sequenceState === 'stalled') {
            $this->customerTaskManager->createJourneyRescueTask(
                $run,
                'La secuencia se degrado y requiere intervencion humana para rescatar continuidad o cierre.'
            );
        }

        return $run;
    }

    public function advanceAfterAutomation(\App\Models\CustomerJourneyRun $run, CustomerTouchpoint $touchpoint): \App\Models\CustomerJourneyRun
    {
        $run = $run->fresh();

        if (! $run instanceof CustomerJourneyRun) {
            throw new \RuntimeException('No se pudo refrescar el run del journey.');
        }

        $conversation = $run->conversation()->withoutGlobalScopes()->first();
        $profile = $run->customerProfile()->withoutGlobalScopes()->with('tenant')->first();

        if (! $conversation instanceof CustomerConversation || ! $profile instanceof CustomerProfile) {
            return $run;
        }

        $steps = collect((array) data_get($run->metadata, 'steps', []))
            ->map(static fn (mixed $step): array => is_array($step) ? $step : [])
            ->values()
            ->all();

        if ($steps === []) {
            return $run;
        }

        $executedAt = Carbon::instance($touchpoint->sent_at ?? now());
        $currentIndex = min(max(0, (int) $run->current_step_index), max(0, count($steps) - 1));
        $nextIndex = min($currentIndex + 1, max(0, count($steps) - 1));
        $hasAdvanced = $nextIndex > $currentIndex;
        $journeyKey = (string) ($run->journey_key ?? $this->journeyKey((string) $run->segment_key, $run->business_segment_key));
        $currentStep = $this->branchResolver->resolve($profile, $conversation, $steps[$nextIndex] ?? null, $journeyKey, $nextIndex);
        $nextStep = $this->branchResolver->resolve($profile, $conversation, $nextIndex < count($steps) - 1 ? ($steps[$nextIndex + 1] ?? null) : null, $journeyKey, $nextIndex + 1);
        $stepDueAt = $this->stepDueAtFromExecution($executedAt, $currentStep, $conversation);

        $conversation->forceFill([
            'status' => 'waiting_customer',
            'last_team_message_at' => $executedAt,
            'next_action_at' => $stepDueAt,
            'escalation_due_at' => $stepDueAt,
        ]);

        $conversation->save();

        $currentStepExecutedAt = $hasAdvanced ? null : $executedAt;
        $sequenceState = 'waiting_customer';
        $dueState = $stepDueAt->isPast() ? 'breached' : 'scheduled';

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
                'business_signals' => data_get($conversation->metadata, 'business_signals', []),
                'last_business_signal' => data_get($conversation->metadata, 'last_business_signal'),
                'last_business_signal_at' => data_get($conversation->metadata, 'last_business_signal_at'),
                'recommended_template_key' => $currentStep['template_key'] ?? $nextStep['template_key'] ?? null,
                'lane' => $currentStep['lane'] ?? data_get($run->metadata, 'lane'),
                'current_branch_key' => $currentStep['branch_key'] ?? 'base',
                'current_branch_label' => $currentStep['branch_label'] ?? 'Ruta base',
                'next_branch_key' => $nextStep['branch_key'] ?? null,
                'next_branch_label' => $nextStep['branch_label'] ?? null,
                'current_sequence_key' => $currentStep['sequence_key'] ?? null,
                'current_sequence_label' => $currentStep['sequence_label'] ?? null,
                'next_sequence_key' => $nextStep['sequence_key'] ?? null,
                'next_sequence_label' => $nextStep['sequence_label'] ?? null,
                'sequence_state_label' => $this->sequenceStateLabel($sequenceState),
                'due_state_label' => $this->dueStateLabel($dueState),
                'step_due_at' => $stepDueAt->toIso8601String(),
                'auto_send' => (bool) ($currentStep['auto_send'] ?? false),
                'current_step_executed_at' => $currentStepExecutedAt !== null ? $currentStepExecutedAt->toIso8601String() : null,
                'last_auto_touchpoint_id' => (int) $touchpoint->getKey(),
            ]),
        ]);

        $run = $this->journeyRuns->save($run);

        return $run;
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
    private function stepDueAtFromExecution(Carbon $executedAt, ?array $step, CustomerConversation $conversation): Carbon
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
