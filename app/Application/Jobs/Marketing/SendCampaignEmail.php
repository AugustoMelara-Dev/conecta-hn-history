<?php

declare(strict_types=1);

namespace App\Application\Jobs\Marketing;

use App\Mail\CampaignOfferMail;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use App\Domain\Shared\Middleware\TenantQueueMiddleware;

final class SendCampaignEmail implements ShouldQueue, ShouldBeUnique
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
        $campaign = Campaign::findOrFail($this->campaignId);
        $user = User::findOrFail($this->userId);
        Mail::to($user->email)->send(new CampaignOfferMail($campaign, $user));
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function tags(): array
    {
        return ['marketing', 'campaign:' . $this->campaignId];
    }
}
