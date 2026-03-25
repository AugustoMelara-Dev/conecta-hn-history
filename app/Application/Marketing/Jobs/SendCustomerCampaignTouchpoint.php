<?php

declare(strict_types=1);

namespace App\Application\Marketing\Jobs;

use App\Domain\TenantGovernance\Services\CustomerAutomationManager;
use App\Domain\TenantGovernance\Services\CustomerLoyaltyManager;
use App\Infrastructure\Queue\Middleware\TenantQueueMiddleware;
use App\Models\Campaign;
use App\Models\CustomerProfile;
use App\Models\LoyaltyReward;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class SendCustomerCampaignTouchpoint implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $campaignId,
        public readonly int $customerProfileId,
    ) {}

    public function uniqueId(): string
    {
        return "customer_campaign_{$this->campaignId}_profile_{$this->customerProfileId}";
    }

    public function handle(CustomerAutomationManager $automationManager, CustomerLoyaltyManager $loyaltyManager): void
    {
        $campaign = Campaign::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->find($this->campaignId);

        $profile = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->find($this->customerProfileId);

        if (! $campaign instanceof Campaign || ! $profile instanceof CustomerProfile) {
            return;
        }

        if ($campaign->loyalty_reward_id !== null) {
            $campaign->setRelation(
                'loyaltyReward',
                LoyaltyReward::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $this->tenantId)
                    ->find($campaign->loyalty_reward_id),
            );
        }

        if (! in_array($campaign->status, ['draft', 'queued', 'scheduled', 'sending'], true)) {
            return;
        }

        $automationKey = 'campaign_'.$campaign->id;
        $actorUserId = $campaign->owner_user_id !== null ? (string) $campaign->owner_user_id : null;
        $rewardContext = $this->rewardContext($campaign->loyaltyReward, $profile, $loyaltyManager);
        $body = $this->buildMessageBody((string) $campaign->content, $rewardContext['copy']);

        if ($campaign->type === 'whatsapp') {
            $automationManager->sendWhatsAppTouchpoint(
                profile: $profile,
                body: $body,
                automationKey: $automationKey,
                actorUserId: $actorUserId,
                options: [
                    'requires_marketing_opt_in' => (bool) $campaign->requires_marketing_opt_in,
                    'template_name' => $campaign->whatsapp_template_name,
                    'template_language' => $campaign->whatsapp_template_language ?: 'es_HN',
                    'template_variables' => array_values(array_filter([
                        (string) ($profile->name ?? 'cliente'),
                        (string) ($campaign->loyaltyReward?->name ?? 'beneficio'),
                    ])),
                    'campaign_id' => $campaign->id,
                    'campaign_subject' => $campaign->subject,
                    'reward_name' => $campaign->loyaltyReward?->name,
                    'reward_points_cost' => $campaign->loyaltyReward?->points_cost,
                    'cta_label' => $rewardContext['cta_label'],
                    'cadence_interval_days' => $campaign->cadence_interval_days,
                ],
            );

            return;
        }

        $automationManager->sendEmailTouchpoint(
            profile: $profile,
            subject: (string) $campaign->subject,
            body: $body,
            automationKey: $automationKey,
            actorUserId: $actorUserId,
            options: [
                'requires_marketing_opt_in' => (bool) $campaign->requires_marketing_opt_in,
                'campaign_id' => $campaign->id,
                'campaign_subject' => $campaign->subject,
                'reward_name' => $campaign->loyaltyReward?->name,
                'reward_points_cost' => $campaign->loyaltyReward?->points_cost,
                'cta_label' => $rewardContext['cta_label'],
                'cadence_interval_days' => $campaign->cadence_interval_days,
            ],
        );
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function tags(): array
    {
        return ['marketing', 'campaign:'.$this->campaignId, 'customer-profile:'.$this->customerProfileId];
    }

    /**
     * @return array{copy: string, cta_label: string|null}
     */
    private function rewardContext(?LoyaltyReward $reward, CustomerProfile $profile, CustomerLoyaltyManager $loyaltyManager): array
    {
        if (! $reward instanceof LoyaltyReward) {
            return [
                'copy' => '',
                'cta_label' => null,
            ];
        }

        $callout = $loyaltyManager->rewardCalloutForProfile($profile, $reward);

        return [
            'copy' => trim($callout['headline'].' '.trim($callout['copy'])),
            'cta_label' => $callout['cta_label'],
        ];
    }

    private function buildMessageBody(string $baseBody, string $rewardCopy): string
    {
        $baseBody = trim($baseBody);
        $rewardCopy = trim($rewardCopy);

        if ($rewardCopy === '') {
            return $baseBody;
        }

        return trim($baseBody."\n\n".$rewardCopy);
    }
}
