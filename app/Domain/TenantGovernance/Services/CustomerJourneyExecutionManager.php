<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerJourneyRunRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\CustomerNoteRepositoryInterface;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;

class CustomerJourneyExecutionManager
{
    public function __construct(
        private readonly CustomerConversationTemplateManager $templateManager,
        private readonly CustomerJourneyManager $journeyManager,
        private readonly CustomerTaskManager $customerTaskManager,
        private readonly CustomerJourneyRunRepositoryInterface $journeyRuns,
        private readonly CustomerNoteRepositoryInterface $customerNotes,
    ) {}

    /**
     * @return array{processed:int,sent:int,skipped:int,failed:int}
     */
    public function dispatchDueJourneys(Tenant|string|null $tenant = null, int $limit = 50): array
    {
        $tenantId = null;

        if ($tenant instanceof Tenant) {
            $tenantId = (string) $tenant->getKey();
        } elseif (is_string($tenant) && $tenant !== '') {
            $tenantId = $tenant;
        }

        $processed = 0;
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        $this->journeyRuns->dueRuns($tenantId, $limit)->each(function (\App\Models\CustomerJourneyRun $run) use (&$processed, &$sent, &$skipped, &$failed): void {
            $processed++;

            $profile = $run->customerProfile;
            $currentStep = data_get($run->metadata, 'current_step');
            $templateKey = $run->next_template_key;

            if (! $profile instanceof CustomerProfile || ! is_array($currentStep) || ! (bool) ($currentStep['auto_send'] ?? false) || ! filled($templateKey)) {
                $this->markSkipped($run, 'El step actual no tiene dispatch automatico listo.');
                $skipped++;

                return;
            }

            if ($run->current_step_executed_at !== null) {
                $skipped++;

                return;
            }

            $note = $this->customerNotes->latestByConversation((int) $run->customer_conversation_id);

            try {
                $touchpoint = $this->templateManager->send(
                    profile: $profile,
                    templateKey: (string) $templateKey,
                    note: $note,
                    actorUserId: null,
                );

                if ($touchpoint->status === CustomerTouchpoint::STATUS_SENT) {
                    $this->journeyManager->advanceAfterAutomation($run, $touchpoint);
                    $sent++;

                    return;
                }

                $this->markSkipped($run, (string) ($touchpoint->last_error ?? 'El touchpoint no pudo enviarse automaticamente.'));
                $skipped++;
            } catch (\Throwable $exception) {
                $this->markFailed($run, $exception->getMessage());
                $failed++;
            }
        });

        return compact('processed', 'sent', 'skipped', 'failed');
    }

    private function markSkipped(\App\Models\CustomerJourneyRun $run, string $reason): void
    {
        $run->forceFill([
            'metadata' => array_merge((array) $run->metadata, [
                'last_auto_status' => 'skipped',
                'last_auto_reason' => $reason,
                'last_auto_checked_at' => now()->toIso8601String(),
            ]),
        ]);

        $this->journeyRuns->save($run);
    }

    private function markFailed(\App\Models\CustomerJourneyRun $run, string $reason): void
    {
        $run->forceFill([
            'sequence_state' => 'stalled',
            'metadata' => array_merge((array) $run->metadata, [
                'last_auto_status' => 'failed',
                'last_auto_reason' => $reason,
                'last_auto_checked_at' => now()->toIso8601String(),
            ]),
        ]);

        $run = $this->journeyRuns->save($run);

        $this->customerTaskManager->createJourneyRescueTask(
            $run,
            'La secuencia automatica fallo: '.$reason
        );
    }
}
