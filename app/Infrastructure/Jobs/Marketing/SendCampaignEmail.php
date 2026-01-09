<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Marketing;

use App\Domain\Shared\Middleware\TenantQueueMiddleware;
use App\Mail\CampaignOfferMail;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

/**
 * SendCampaignEmail - Marketing Campaign Email Sender
 *
 * INFRASTRUCTURE JOB: External mail service integration requires direct ORM.
 */
final class SendCampaignEmail implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $campaignId,
        public readonly int $userId
    ) {}

    public function uniqueId(): string
    {
        return "campaign_{$this->campaignId}_user_{$this->userId}";
    }

    public function handle(): void
    {
        // 1. Context Check (Redundant with Middleware but good for defense in depth)
        // Note: Middleware handles DB connection, but we just verify we have IDs.

        // 2. Hydration with Fail Fast/Silent Pattern
        $campaign = Campaign::find($this->campaignId);
        $user = User::find($this->userId);

        // 3. Guard Clause: Fail Silently if resources are gone (prevent retry spirals)
        if (! $campaign || ! $user) {
            // Log warning to avoid total silence, but return success to queue to stop retries
            \Illuminate\Support\Facades\Log::warning('SendCampaignEmail: Aborted - Resource missing', [
                'campaign_id' => $this->campaignId,
                'user_id' => $this->userId,
                'campaign_found' => (bool) $campaign,
                'user_found' => (bool) $user,
            ]);
            
            return;
        }

        Mail::to($user->email)->send(new CampaignOfferMail($campaign, $user));
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function tags(): array
    {
        return ['marketing', 'campaign:'.$this->campaignId];
    }
}
