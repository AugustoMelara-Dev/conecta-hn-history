<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Marketing\Jobs\DispatchCampaignSegment;
use App\Models\Campaign;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DispatchDueCustomerCampaignsCommand extends Command
{
    protected $signature = 'crm:dispatch-due-campaigns {--tenant=} {--limit=100}';

    protected $description = 'Dispatch scheduled CRM campaigns that are due now.';

    public function handle(): int
    {
        $now = now();
        $processed = 0;
        $queued = 0;

        $campaigns = Campaign::query()
            ->withoutGlobalScopes()
            ->when($this->option('tenant'), fn ($query, $tenantId) => $query->where('tenant_id', (string) $tenantId))
            ->where(function ($query) use ($now): void {
                $query->where(function ($scheduled) use ($now): void {
                    $scheduled
                        ->where('status', 'scheduled')
                        ->whereNotNull('scheduled_at')
                        ->where('scheduled_at', '<=', $now);
                })->orWhere(function ($recurring) use ($now): void {
                    $recurring
                        ->where('is_recurring', true)
                        ->whereNotNull('next_run_at')
                        ->where('next_run_at', '<=', $now);
                });
            })
            ->orderBy('next_run_at')
            ->orderBy('scheduled_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        foreach ($campaigns as $campaign) {
            $processed++;
            $this->queueCampaign($campaign, $now);
            $queued++;
        }

        $this->info("Processed {$processed} campaigns. Queued {$queued} dispatches.");

        return self::SUCCESS;
    }

    private function queueCampaign(Campaign $campaign, Carbon $now): void
    {
        if ($campaign->usesCadence()) {
            DispatchCampaignSegment::dispatch(
                (int) $campaign->getKey(),
                (string) $campaign->tenant_id,
                $this->runKey($campaign, $now),
            );

            $campaign->forceFill([
                'status' => 'scheduled',
                'last_dispatched_at' => $now,
                'next_run_at' => $now->copy()->addDays((int) $campaign->cadence_interval_days),
            ])->save();
        } else {
            DispatchCampaignSegment::dispatch(
                (int) $campaign->getKey(),
                (string) $campaign->tenant_id,
                $this->runKey($campaign, $now),
            );

            $campaign->forceFill([
                'status' => 'queued',
                'last_dispatched_at' => $now,
                'next_run_at' => null,
            ])->save();
        }
    }

    private function runKey(Campaign $campaign, Carbon $now): string
    {
        return sprintf(
            'campaign_%s_%s_%s',
            $campaign->getKey(),
            $now->format('YmdHis'),
            Str::lower(Str::random(8)),
        );
    }
}
