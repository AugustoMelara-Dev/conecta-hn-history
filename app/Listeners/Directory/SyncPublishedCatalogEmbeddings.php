<?php

declare(strict_types=1);

namespace App\Listeners\Directory;

use App\Events\CatalogPublishedChanged;
use App\Jobs\Directory\SyncTenantDirectoryEmbeddingsJob;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SyncPublishedCatalogEmbeddings implements ShouldQueue
{
    public int $tries = 3;

    public function handle(CatalogPublishedChanged $event): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        SyncTenantDirectoryEmbeddingsJob::dispatch(
            tenantId: $event->tenantId,
            sinceHours: 48,
        );
    }
}
