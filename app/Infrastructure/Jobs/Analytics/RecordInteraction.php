<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Analytics;

use App\Models\Interaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * RecordInteraction - Event Sourcing Job
 *
 * INFRASTRUCTURE JOB: Creates interaction records for analytics.
 */
final class RecordInteraction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $eventType,
        public readonly array $metadata = []
    ) {}

    public function handle(): void
    {
        Interaction::create([
            'tenant_id' => $this->tenantId,
            'event_type' => $this->eventType,
            'metadata' => $this->metadata,
            'recorded_at' => now(),
        ]);
    }

    public function tags(): array
    {
        return ['analytics', 'tenant:'.$this->tenantId];
    }
}
