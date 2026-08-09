<?php

declare(strict_types=1);

namespace App\Application\Marketing\Commands;

use App\Application\Marketing\CampaignDispatchService;
use Illuminate\Console\Command;

final class DispatchDueCampaignsCommand extends Command
{
    protected $signature = 'campaigns:dispatch-due {--limit=25}';

    protected $description = 'Claim due marketing campaigns and queue their tenant-scoped deliveries';

    public function handle(CampaignDispatchService $campaigns): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($limit === false) {
            $this->error('The --limit option must be between 1 and 100.');

            return self::FAILURE;
        }

        $stats = $campaigns->dispatchDue($limit);
        $this->info("Claimed: {$stats['claimed']}, queued: {$stats['queued']}, empty: {$stats['completed_empty']}, errors: {$stats['errors']}");

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
