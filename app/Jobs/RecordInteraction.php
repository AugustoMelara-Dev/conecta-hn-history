<?php

namespace App\Jobs;

use App\Models\Interaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * RecordInteraction - Non-blocking Analytics Writer
 *
 * PERFORMANCE ARCHITECTURE:
 * This job decouples the HTTP response from I/O-bound analytics writes.
 * Instead of blocking the user's request for 50-200ms while writing to DB,
 * we return immediately and process the write asynchronously.
 *
 * SERIALIZATION OPTIMIZATION:
 * - Does NOT use SerializesModels trait (no Eloquent models in payload)
 * - Uses only primitive types (int, string, array) for minimal serialization overhead
 * - Reduces payload size and avoids Reflection API overhead
 *
 * DISPATCH STRATEGY:
 * - Pure async dispatch: RecordInteraction::dispatch(...)
 * - Controller returns immediately, job processed by queue worker
 * - Do NOT use ->afterResponse() as it still ties up PHP-FPM worker
 *
 * USAGE:
 *   // Fire and forget - user gets response immediately
 *   RecordInteraction::dispatch($tenantId, 'whatsapp_click', $productId);
 */
class RecordInteraction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    // NOTE: SerializesModels intentionally omitted - we only use primitives

    /**
     * The number of times the job may be attempted.
     * Analytics are non-critical - don't retry excessively.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 5;

    /**
     * Create a new job instance.
     *
     * PRIMITIVE-ONLY SERIALIZATION:
     * All parameters are scalar types or arrays of scalars.
     * This eliminates the need for model serialization overhead.
     *
     * @param  int  $tenantId  The tenant owning this interaction
     * @param  string  $type  The interaction type (whatsapp_click, page_view, etc.)
     * @param  int|null  $productId  Optional product context
     * @param  array  $metadata  Additional contextual data
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly string $type,
        public readonly ?int $productId = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Execute the job.
     *
     * Delegates to the trusted Interaction::record factory method,
     * which uses forceFill() to bypass guarded protection safely.
     */
    public function handle(): void
    {
        Interaction::record(
            tenantId: $this->tenantId,
            type: $this->type,
            productId: $this->productId,
            metadata: $this->metadata,
        );
    }

    /**
     * Handle a job failure.
     *
     * Analytics failures are non-critical.
     * Log but don't escalate - we don't want to wake anyone up
     * at 3 AM because we missed a page view counter.
     */
    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::warning(
            'Failed to record interaction',
            [
                'tenant_id' => $this->tenantId,
                'type' => $this->type,
                'product_id' => $this->productId,
                'error' => $exception->getMessage(),
            ]
        );
    }
}
