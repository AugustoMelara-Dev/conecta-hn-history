<?php

declare(strict_types=1);

namespace App\Application\Marketing\Jobs;

use App\Domain\Marketing\Enums\AudienceType;
use App\Infrastructure\Jobs\Marketing\SendCampaignEmail;
use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * DispatchCampaignSegment - Fanning Out Marketing Campaigns
 *
 * Resolves the audience segment into individual user IDs and pushes
 * individual SendCampaignEmail jobs to the queue.
 *
 * Lives in Application because it orchestrates infra-facing concerns.
 */
final class DispatchCampaignSegment implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $campaignId,
        public readonly string $tenantId
    ) {}

    public function uniqueId(): string
    {
        return "dispatch_campaign_{$this->campaignId}";
    }

    public function handle(): void
    {
        $campaign = Campaign::where('id', $this->campaignId)
            ->where('tenant_id', $this->tenantId)
            ->first();

        if (! $campaign || $campaign->status !== 'draft') {
            return;
        }

        $userIds = $this->resolveAudience($this->tenantId, $campaign->audience_type);

        if (empty($userIds)) {
            $campaign->update(['status' => 'failed', 'recipients_count' => 0]);

            return;
        }

        foreach ($userIds as $userId) {
            SendCampaignEmail::dispatch($this->tenantId, $campaign->id, $userId);
        }

        $campaign->update([
            'status' => 'sending',
            'sent_at' => now(),
            'recipients_count' => count($userIds),
        ]);
    }

    private function resolveAudience(string $tenantId, ?AudienceType $type): array
    {
        return match ($type) {
            AudienceType::FOLLOWERS, null => DB::table('follows')
                ->where('tenant_id', $tenantId)
                ->distinct()
                ->pluck('user_id')
                ->toArray(),

            AudienceType::ALL_CUSTOMERS => DB::table('appointments')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id')
                ->toArray(),

            AudienceType::WAITLIST => DB::table('waitlists')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id')
                ->toArray(),

            AudienceType::LEADS => DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->join('users', 'leads.email', '=', 'users.email')
                ->distinct()
                ->pluck('users.id')
                ->toArray(),
        };
    }
}
