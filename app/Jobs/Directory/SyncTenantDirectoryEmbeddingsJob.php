<?php

declare(strict_types=1);

namespace App\Jobs\Directory;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

final class SyncTenantDirectoryEmbeddingsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $sinceHours = 48,
    ) {}

    public function handle(): void
    {
        Artisan::call('directory:sync-embeddings', [
            '--tenant' => $this->tenantId,
            '--since-hours' => max(1, $this->sinceHours),
        ]);
    }
}
