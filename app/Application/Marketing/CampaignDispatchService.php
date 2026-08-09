<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Infrastructure\Jobs\Marketing\SendCampaignEmail;
use App\Models\Campaign;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CampaignDispatchService
{
    public function schedule(Campaign $campaign, CarbonInterface $scheduledAt): void
    {
        if (! $campaign->isDispatchable()) {
            throw new InvalidArgumentException('La campaña necesita asunto, contenido y un canal disponible.');
        }

        $campaign->forceFill([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
            'sent_at' => null,
            'dispatch_started_at' => null,
            'completed_at' => null,
            'failure_reason' => null,
        ])->save();
    }

    public function pause(Campaign $campaign): void
    {
        if ($campaign->status !== 'scheduled') {
            throw new InvalidArgumentException('Solo una campaña programada puede pausarse.');
        }

        $campaign->forceFill(['status' => 'paused'])->save();
    }

    /** @return array{claimed: int, queued: int, completed_empty: int, errors: int} */
    public function dispatchDue(int $limit = 25): array
    {
        $stats = ['claimed' => 0, 'queued' => 0, 'completed_empty' => 0, 'errors' => 0];
        $campaignIds = DB::table('campaigns')
            ->where('status', 'scheduled')
            ->where('type', 'email')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(max(1, min($limit, 100)))
            ->pluck('id');

        foreach ($campaignIds as $campaignId) {
            try {
                $campaign = $this->claim((int) $campaignId);
                if ($campaign === null) {
                    continue;
                }

                $stats['claimed']++;
                $recipientIds = DB::table('follows as f')
                    ->join('users as u', 'u.id', '=', 'f.user_id')
                    ->where('f.tenant_id', $campaign->tenant_id)
                    ->whereNotNull('u.email')
                    ->where('u.email', '<>', '')
                    ->orderBy('u.id')
                    ->pluck('u.id')
                    ->map(static fn (mixed $id): string => (string) $id)
                    ->unique()
                    ->values();

                foreach ($recipientIds as $userId) {
                    DB::table('campaign_deliveries')->insertOrIgnore([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $campaign->tenant_id,
                        'campaign_id' => $campaign->id,
                        'user_id' => $userId,
                        'status' => 'pending',
                        'attempt_count' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $recipientCount = DB::table('campaign_deliveries')
                    ->where('tenant_id', $campaign->tenant_id)
                    ->where('campaign_id', $campaign->id)
                    ->count();

                DB::table('campaigns')->where('id', $campaign->id)->where('tenant_id', $campaign->tenant_id)->update([
                    'recipients_count' => $recipientCount,
                    'updated_at' => now(),
                ]);

                if ($recipientCount === 0) {
                    DB::table('campaigns')->where('id', $campaign->id)->where('tenant_id', $campaign->tenant_id)->update([
                        'status' => 'completed',
                        'sent_at' => now(),
                        'completed_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $stats['completed_empty']++;

                    continue;
                }

                foreach ($recipientIds as $userId) {
                    SendCampaignEmail::dispatch(
                        tenantId: (string) $campaign->tenant_id,
                        campaignId: (int) $campaign->id,
                        userId: $userId,
                    );
                    $stats['queued']++;
                }
            } catch (\Throwable) {
                DB::table('campaigns')->where('id', $campaignId)->where('status', 'dispatching')->update([
                    'status' => 'failed',
                    'failure_reason' => 'campaign_dispatch_failed',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
                $stats['errors']++;
            }
        }

        return $stats;
    }

    private function claim(int $campaignId): ?object
    {
        return DB::transaction(function () use ($campaignId): ?object {
            $campaign = DB::table('campaigns')->where('id', $campaignId)->lockForUpdate()->first();
            if (! is_object($campaign)
                || $campaign->status !== 'scheduled'
                || $campaign->type !== 'email'
                || $campaign->scheduled_at === null
                || now()->lt($campaign->scheduled_at)) {
                return null;
            }

            DB::table('campaigns')->where('id', $campaignId)->where('status', 'scheduled')->update([
                'status' => 'dispatching',
                'dispatch_started_at' => now(),
                'delivered_count' => 0,
                'failed_count' => 0,
                'blocked_count' => 0,
                'failure_reason' => null,
                'updated_at' => now(),
            ]);

            return DB::table('campaigns')->where('id', $campaignId)->first();
        });
    }
}
