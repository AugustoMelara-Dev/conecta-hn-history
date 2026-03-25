<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerConversation;
use App\Models\CustomerProfile;
use Illuminate\Support\Carbon;

class CustomerJourneyBranchResolver
{
    /**
     * @param  array<string, mixed>|null  $step
     * @return array<string, mixed>|null
     */
    public function resolve(
        CustomerProfile $profile,
        ?CustomerConversation $conversation,
        ?array $step,
        string $journeyKey,
        int $stepIndex = 0,
    ): ?array {
        if ($step === null) {
            return null;
        }

        $resolved = $step;
        $resolved['sequence_key'] = $this->sequenceKey($journeyKey, $step, null);
        $resolved['sequence_label'] = (string) ($step['title'] ?? 'Secuencia CRM');
        $resolved['branch_key'] = 'base';
        $resolved['branch_label'] = 'Ruta base';

        foreach ((array) ($step['branches'] ?? []) as $branch) {
            if (! is_array($branch) || ! $this->matches($profile, $conversation, $branch)) {
                continue;
            }

            $branchKey = (string) ($branch['key'] ?? "branch_{$stepIndex}");
            $branchLabel = (string) ($branch['label'] ?? 'Ruta priorizada');
            $overrides = collect($branch)
                ->except(['key', 'label', 'when', 'any_of'])
                ->all();

            $resolved = array_replace($resolved, $overrides);
            $resolved['branch_key'] = $branchKey;
            $resolved['branch_label'] = $branchLabel;
            $resolved['sequence_key'] = $this->sequenceKey($journeyKey, $resolved, $branchKey);
            $resolved['sequence_label'] = (string) ($resolved['title'] ?? $step['title'] ?? 'Secuencia CRM');

            break;
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $branch
     */
    private function matches(CustomerProfile $profile, ?CustomerConversation $conversation, array $branch): bool
    {
        $all = array_values(array_filter((array) ($branch['when'] ?? []), 'is_string'));
        $any = array_values(array_filter((array) ($branch['any_of'] ?? []), 'is_string'));

        if (is_string($branch['when'] ?? null)) {
            $all = [(string) $branch['when']];
        }

        if ($all !== []) {
            foreach ($all as $condition) {
                if (! $this->conditionMet($profile, $conversation, $condition)) {
                    return false;
                }
            }
        }

        if ($any !== []) {
            foreach ($any as $condition) {
                if ($this->conditionMet($profile, $conversation, $condition)) {
                    return true;
                }
            }

            return false;
        }

        return $all !== [];
    }

    private function conditionMet(CustomerProfile $profile, ?CustomerConversation $conversation, string $condition): bool
    {
        return match ($condition) {
            'reward_ready' => (int) $profile->redeemable_rewards_count > 0,
            'vip_customer' => (string) ($profile->loyalty_tier ?? '') === 'vip' || (string) ($profile->segment_display ?? '') === 'vip',
            'high_value_customer' => (float) ($profile->total_spent ?? 0) >= 1000
                || (float) ($profile->initial_ltv ?? 0) >= 500
                || (float) ($profile->average_ticket ?? 0) >= 300,
            'whatsapp_preferred' => (string) ($profile->preferred_channel ?? '') === 'whatsapp'
                && filled($profile->phone)
                && (bool) $profile->whatsapp_opt_in,
            'email_preferred' => (string) ($profile->preferred_channel ?? '') === 'email'
                && filled($profile->email)
                && (bool) $profile->email_opt_in,
            'stalled_sequence' => $this->isStalled($conversation),
            'multiple_replies' => (int) ($conversation?->reply_count ?? 0) >= 2,
            'high_reactivation_risk' => (int) ($profile->reactivation_score ?? 0) >= 70,
            'closing_ready' => (int) ($profile->orders_count ?? 0) > 0
                || (int) ($profile->appointments_count ?? 0) > 0
                || (float) ($profile->total_spent ?? 0) > 0,
            default => false,
        };
    }

    private function isStalled(?CustomerConversation $conversation): bool
    {
        if (! $conversation instanceof CustomerConversation) {
            return false;
        }

        $deadline = $conversation->next_action_at ?? $conversation->response_due_at;

        return $deadline instanceof Carbon && $deadline->isPast();
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function sequenceKey(string $journeyKey, array $step, ?string $branchKey): string
    {
        $stepKey = (string) ($step['key'] ?? 'step');
        $suffix = $branchKey !== null && $branchKey !== '' ? $branchKey : 'base';

        return "{$journeyKey}:{$stepKey}:{$suffix}";
    }
}
