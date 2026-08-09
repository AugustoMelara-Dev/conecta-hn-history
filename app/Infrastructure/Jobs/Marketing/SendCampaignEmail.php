<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Marketing;

use App\Application\Shared\DTOs\Mail\CampaignMailData;
use App\Application\Shared\DTOs\Mail\UserMailData;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Mail\CampaignOfferMail;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

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
        public readonly string $tenantId,
        public readonly int $campaignId,
        public readonly string $userId
    ) {}

    public function uniqueId(): string
    {
        return "campaign_{$this->tenantId}_{$this->campaignId}_user_{$this->userId}";
    }

    public function handle(): void
    {
        $campaign = Campaign::query()
            ->with('tenant')
            ->where('id', $this->campaignId)
            ->where('tenant_id', $this->tenantId)
            ->first();

        if (! $campaign) {
            Log::warning('Campaign delivery aborted', ['reason' => 'campaign_not_found']);

            return;
        }

        DB::table('campaign_deliveries')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantId,
            'campaign_id' => $this->campaignId,
            'user_id' => $this->userId,
            'status' => 'pending',
            'attempt_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $delivery = DB::table('campaign_deliveries')
            ->where('tenant_id', $this->tenantId)
            ->where('campaign_id', $this->campaignId)
            ->where('user_id', $this->userId)
            ->first();

        if (! is_object($delivery)) {
            throw new \RuntimeException('Campaign delivery intent could not be persisted.');
        }

        if (in_array((string) $delivery->status, ['delivered', 'blocked'], true)) {
            return;
        }

        $user = User::query()->find($this->userId);
        $isCurrentFollower = $user !== null && DB::table('follows')
            ->where('user_id', $this->userId)
            ->where('tenant_id', $this->tenantId)
            ->exists();

        if (! $isCurrentFollower || ! $user || trim((string) $user->email) === '') {
            DB::table('campaign_deliveries')->where('id', $delivery->id)->update([
                'status' => 'blocked',
                'failure_reason' => 'recipient_not_eligible',
                'attempted_at' => now(),
                'updated_at' => now(),
            ]);
            $this->finalizeCampaign();

            return;
        }

        DB::table('campaign_deliveries')->where('id', $delivery->id)->update([
            'status' => 'pending',
            'attempt_count' => DB::raw('attempt_count + 1'),
            'attempted_at' => now(),
            'failure_reason' => null,
            'updated_at' => now(),
        ]);

        Mail::to($user->email)->send(new CampaignOfferMail(
            CampaignMailData::fromCampaign($campaign),
            UserMailData::fromUser($user),
        ));

        DB::table('campaign_deliveries')->where('id', $delivery->id)->update([
            'status' => 'delivered',
            'delivered_at' => now(),
            'failure_reason' => null,
            'updated_at' => now(),
        ]);
        $this->finalizeCampaign();
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('campaign_deliveries')
            ->where('tenant_id', $this->tenantId)
            ->where('campaign_id', $this->campaignId)
            ->where('user_id', $this->userId)
            ->where('status', '<>', 'delivered')
            ->update([
                'status' => 'failed',
                'failure_reason' => 'mail_transport_failed',
                'updated_at' => now(),
            ]);
        $this->finalizeCampaign();
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function tags(): array
    {
        return ['marketing', 'campaign:'.$this->campaignId];
    }

    private function finalizeCampaign(): void
    {
        $counts = DB::table('campaign_deliveries')
            ->where('tenant_id', $this->tenantId)
            ->where('campaign_id', $this->campaignId)
            ->selectRaw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->selectRaw("SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked_count")
            ->first();
        $campaign = DB::table('campaigns')
            ->where('id', $this->campaignId)
            ->where('tenant_id', $this->tenantId)
            ->first();

        if (! is_object($campaign) || ! is_object($counts)) {
            return;
        }

        $delivered = (int) $counts->delivered_count;
        $failed = (int) $counts->failed_count;
        $blocked = (int) $counts->blocked_count;
        $updates = [
            'delivered_count' => $delivered,
            'failed_count' => $failed,
            'blocked_count' => $blocked,
            'updated_at' => now(),
        ];

        if ((int) $campaign->recipients_count > 0
            && $delivered + $failed + $blocked >= (int) $campaign->recipients_count) {
            $updates['status'] = $failed + $blocked > 0 ? 'completed_with_errors' : 'completed';
            $updates['sent_at'] = now();
            $updates['completed_at'] = now();
            $updates['failure_reason'] = $failed > 0
                ? 'some_deliveries_failed'
                : ($blocked > 0 ? 'some_recipients_blocked' : null);
        }

        DB::table('campaigns')
            ->where('id', $this->campaignId)
            ->where('tenant_id', $this->tenantId)
            ->update($updates);
    }
}
