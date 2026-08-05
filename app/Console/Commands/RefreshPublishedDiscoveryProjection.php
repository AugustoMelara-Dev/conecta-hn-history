<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Discovery\PublishedCommercialProjectionService;
use Illuminate\Console\Command;

final class RefreshPublishedDiscoveryProjection extends Command
{
    protected $signature = 'dovra:discover-project';

    protected $description = 'Refresh the published commercial discovery projection.';

    public function handle(PublishedCommercialProjectionService $projection): int
    {
        $projection->projectCurrent();

        $this->info('Published discovery projection refreshed.');

        return self::SUCCESS;
    }
}
