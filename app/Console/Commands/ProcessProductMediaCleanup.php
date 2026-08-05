<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Products\ProductMediaCleanupQueue;
use Illuminate\Console\Command;

final class ProcessProductMediaCleanup extends Command
{
    protected $signature = 'storage:process-product-media-cleanup {--limit=100 : Maximum cleanup tasks to process}';

    protected $description = 'Process durable idempotent product-media cleanup tasks';

    public function handle(ProductMediaCleanupQueue $queue): int
    {
        $result = $queue->drain((int) $this->option('limit'));
        $this->info("{$result['processed']} product media cleanup task(s) processed.");

        if ($result['failed'] > 0) {
            $this->warn("{$result['failed']} task(s) deferred for retry.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
