<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerJourneyRun;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;

class CustomerJourneyExecutionManager
{
    public function __construct(
        private readonly CustomerConversationTemplateManager $templateManager,
        private readonly CustomerJourneyManager $journeyManager,
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

        $query = CustomerJourneyRun::query()
            ->withoutGlobalScopes()
            ->with([
                'conversation' => fn ($builder) => $builder->withoutGlobalScopes(),
                'customerProfile' => fn ($builder) => $builder->withoutGlobalScopes()->with('tenant'),
            ])
            ->where('status', 'active')
            ->whereIn('sequence_state', ['waiting_customer', 'stalled'])
            ->whereNotNull('step_due_at')
            ->where('step_due_at', '<=', now())
            ->orderBy('step_due_at')
            ->limit(max(1, $limit));

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $processed = 0;
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        $query->get()->each(function (CustomerJourneyRun $run) use (&$processed, &$sent, &$skipped, &$failed): void {
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

            $note = CustomerNote::query()
                ->withoutGlobalScopes()
                ->where('customer_conversation_id', (int) $run->customer_conversation_id)
                ->latest('updated_at')
                ->latest('id')
                ->first();

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

    private function markSkipped(CustomerJourneyRun $run, string $reason): void
    {
        $run->forceFill([
            'metadata' => array_merge((array) $run->metadata, [
                'last_auto_status' => 'skipped',
                'last_auto_reason' => $reason,
                'last_auto_checked_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    private function markFailed(CustomerJourneyRun $run, string $reason): void
    {
        $run->forceFill([
            'sequence_state' => 'stalled',
            'metadata' => array_merge((array) $run->metadata, [
                'last_auto_status' => 'failed',
                'last_auto_reason' => $reason,
                'last_auto_checked_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }
}
