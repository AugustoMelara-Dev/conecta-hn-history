<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Ai\Contracts\AssistantRuntime;
use App\Application\Ai\DTOs\AssistantExecutionContext;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Models\AiAssistantRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * GenerateBusinessProfileJob - Async Wrapper for AI Profile Generation
 *
 * ╔═══════════════════════════════════════════════════════════════════════════╗
 * ║  PURPOSE: Offload blocking OpenAI calls to Queue Workers                  ║
 * ╠═══════════════════════════════════════════════════════════════════════════╣
 * ║  Uses the canonical AI gateway for registry selection, schema validation, ║
 * ║  budget accounting and encrypted idempotent result persistence.           ║
 * ╚═══════════════════════════════════════════════════════════════════════════╝
 *
 * SECURITY: Provider calls and budget accounting remain inside AiGateway.
 */
class GenerateBusinessProfileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Job timeout: Accommodate up to 12s backoff + 30s API call + buffer.
     */
    public int $timeout = 60;

    /**
     * Gateway correlation is idempotent; job-level retry remains disabled so
     * operational state is explicit instead of hiding provider unavailability.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     *
     * All properties are scalar for safe serialization to Redis/Database queues.
     */
    public readonly string $correlationId;

    public function __construct(
        public readonly string $businessName,
        public readonly string $description,
        public readonly string $userId,
        public readonly string $tenantId,
        public readonly ?string $targetAudience = null,
        public readonly ?string $tone = null,
        public readonly array $keywords = [],
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? (string) Str::uuid();
    }

    /**
     * Execute the job.
     *
     * The canonical runtime owns provider selection, validation, billing and
     * encrypted result persistence. This job stores only a correlation record.
     */
    public function handle(AssistantRuntime $runtime): void
    {
        Log::info('[GenerateBusinessProfileJob] Starting profile generation.', [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'business_name' => $this->businessName,
        ]);

        $context = new AssistantExecutionContext(
            tenantId: $this->tenantId,
            actorId: $this->userId,
            correlationId: $this->correlationId,
        );
        $result = $runtime->execute('business_profile', [
            'business_name' => $this->businessName,
            'description' => $this->description,
            'target_audience' => $this->targetAudience,
            'tone' => $this->tone,
            'keywords' => $this->keywords,
        ], $context);

        AiAssistantRun::updateOrCreate([
            'tenant_id' => $this->tenantId,
            'assistant_key' => 'business_profile',
            'correlation_id' => $this->correlationId,
        ], [
            'user_id' => $this->userId,
            'prompt' => null,
            'response' => null,
            'cost_usd' => $result->costUsd,
            'status' => $result->success ? 'completed' : 'unavailable',
            'duration_ms' => $result->durationMs,
            'error_message' => $result->success ? null : $result->errorMessage,
            'metadata' => array_merge($result->metadata, ['correlation_id' => $this->correlationId]),
        ]);

        Log::info('[GenerateBusinessProfileJob] Profile generation finished.', [
            'tenant_id' => $this->tenantId,
            'correlation_id' => $this->correlationId,
            'status' => $result->success ? 'completed' : 'unavailable',
        ]);
    }

    /**
     * Handle job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('[GenerateBusinessProfileJob] Job failed.', [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'error' => $exception?->getMessage(),
        ]);
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }
}
