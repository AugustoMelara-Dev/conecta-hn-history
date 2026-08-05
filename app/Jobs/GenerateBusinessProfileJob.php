<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Services\AI\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * GenerateBusinessProfileJob - Async Wrapper for AI Profile Generation
 *
 * ╔═══════════════════════════════════════════════════════════════════════════╗
 * ║  PURPOSE: Offload blocking OpenAI calls to Queue Workers                  ║
 * ╠═══════════════════════════════════════════════════════════════════════════╣
 * ║  PROBLEM SOLVED:                                                          ║
 * ║  OpenAIService::generateStructuredProfile() uses usleep() for backoff,   ║
 * ║  blocking PHP-FPM workers for up to 12 seconds. This causes:             ║
 * ║    - User-facing request timeouts                                         ║
 * ║    - Worker pool exhaustion under load                                    ║
 * ║                                                                           ║
 * ║  SOLUTION:                                                                ║
 * ║  Dispatch this Job to a queue. Queue workers handle the blocking wait,   ║
 * ║  freeing web workers for user requests.                                  ║
 * ╚═══════════════════════════════════════════════════════════════════════════╝
 *
 * SECURITY: All BudgetEnforcer logic (reserve/settle/refund) remains in OpenAIService.
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
     * Retries: Let OpenAIService handle internal retries.
     * Job-level retry disabled to prevent duplicate billing.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     *
     * All properties are scalar for safe serialization to Redis/Database queues.
     */
    public function __construct(
        public readonly string $businessName,
        public readonly string $description,
        public readonly string $userId,
        public readonly string $tenantId,
        public readonly ?string $targetAudience = null,
        public readonly ?string $tone = null,
        public readonly array $keywords = []
    ) {}

    /**
     * Execute the job.
     *
     * @throws \App\Exceptions\Ai\AiProviderUnavailableException
     * @throws \App\Exceptions\Ai\AiMalformedResponseException
     * @throws \App\Exceptions\InsufficientAiCreditsException
     */
    public function handle(OpenAIService $openAIService): void
    {
        Log::info('[GenerateBusinessProfileJob] Starting profile generation.', [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'business_name' => $this->businessName,
        ]);

        $result = $openAIService->generateStructuredProfile(
            businessName: $this->businessName,
            description: $this->description,
            userId: $this->userId,
            tenantId: $this->tenantId,
            targetAudience: $this->targetAudience,
            tone: $this->tone,
            keywords: $this->keywords
        );

        // TODO: Implement result persistence.
        // The calling context must define where to store the generated profile.
        // Options:
        //   1. Fire an event: ProfileGeneratedEvent::dispatch($this->tenantId, $result);
        //   2. Update a model: Tenant::find($this->tenantId)->update(['profile' => $result]);
        //   3. Store in cache: Cache::put("profile_{$this->tenantId}", $result, 3600);

        Log::info('[GenerateBusinessProfileJob] Profile generation completed.', [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'seo_title' => $result['seo_title'],
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
