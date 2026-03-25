<?php

declare(strict_types=1);

namespace App\Application\Marketing\Jobs;

use App\Domain\Marketing\Enums\AudienceType;
use App\Domain\TenantGovernance\Services\CustomerCampaignAudienceResolver;
use App\Infrastructure\Jobs\Marketing\SendCampaignEmail;
use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class DispatchCampaignSegment implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $campaignId,
        public readonly string $tenantId,
    ) {}

    public function uniqueId(): string
    {
        return "dispatch_campaign_{$this->campaignId}";
    }

    public function handle(CustomerCampaignAudienceResolver $audienceResolver): void
    {
        $campaign = Campaign::query()
            ->withoutGlobalScopes()
            ->where('id', $this->campaignId)
            ->where('tenant_id', $this->tenantId)
            ->first();

        if (! $campaign || ! in_array($campaign->status, ['draft', 'queued', 'scheduled'], true)) {
            return;
        }

        if ($campaign->audience_type === AudienceType::CRM_CUSTOMERS) {
            $profileIds = $audienceResolver->profileIdsForCampaign($this->tenantId, $campaign);

            if ($profileIds === []) {
                $this->markEmptyAudience($campaign);

                return;
            }

            foreach ($profileIds as $profileId) {
                SendCustomerCampaignTouchpoint::dispatch($this->tenantId, $campaign->id, $profileId);
            }

            $this->finalizeDispatch($campaign, count($profileIds));

            return;
        }

        $userIds = $this->resolveAudience($this->tenantId, $campaign->audience_type);

        if ($userIds === []) {
            $this->markEmptyAudience($campaign);

            return;
        }

        foreach ($userIds as $userId) {
            SendCampaignEmail::dispatch($this->tenantId, $campaign->id, (string) $userId);
        }

        $this->finalizeDispatch($campaign, count($userIds));
    }

    /**
     * @return array<int, string>
     */
    private function resolveAudience(string $tenantId, ?AudienceType $type): array
    {
        return match ($type) {
            AudienceType::FOLLOWERS, null => DB::table('follows')
                ->where('tenant_id', $tenantId)
                ->distinct()
                ->pluck('user_id')
                ->map(static fn (mixed $value): string => (string) $value)
                ->toArray(),

            AudienceType::ALL_CUSTOMERS => DB::table('appointments')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id')
                ->map(static fn (mixed $value): string => (string) $value)
                ->toArray(),

            AudienceType::WAITLIST => DB::table('waitlists')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id')
                ->map(static fn (mixed $value): string => (string) $value)
                ->toArray(),

            AudienceType::LEADS => DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->join('users', 'leads.email', '=', 'users.email')
                ->distinct()
                ->pluck('users.id')
                ->map(static fn (mixed $value): string => (string) $value)
                ->toArray(),

            AudienceType::CRM_CUSTOMERS => [],
        };
    }

    private function finalizeDispatch(Campaign $campaign, int $recipientsCount): void
    {
        $now = now();

        $campaign->update([
            'status' => $campaign->usesCadence() ? 'scheduled' : 'sending',
            'sent_at' => $now,
            'last_dispatched_at' => $now,
            'next_run_at' => $campaign->usesCadence() ? $this->resolveNextRunAt($campaign, $now) : null,
            'recipients_count' => $recipientsCount,
        ]);
    }

    private function markEmptyAudience(Campaign $campaign): void
    {
        if ($campaign->usesCadence()) {
            $now = now();

            $campaign->update([
                'status' => 'scheduled',
                'last_dispatched_at' => $now,
                'next_run_at' => $this->resolveNextRunAt($campaign, $now),
                'recipients_count' => 0,
            ]);

            return;
        }

        $campaign->update([
            'status' => 'failed',
            'recipients_count' => 0,
        ]);
    }

    private function resolveNextRunAt(Campaign $campaign, Carbon $fallback): ?Carbon
    {
        if (! $campaign->usesCadence()) {
            return null;
        }

        if ($campaign->next_run_at instanceof Carbon && $campaign->next_run_at->isFuture()) {
            return $campaign->next_run_at;
        }

        return $fallback->copy()->addDays((int) $campaign->cadence_interval_days);
    }
}
