<?php

declare(strict_types=1);

namespace App\Application\Jobs\AI;

use App\Models\Tenant;
use App\Services\AiProfileGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use App\Domain\Shared\Middleware\TenantQueueMiddleware;

final class GenerateBusinessProfile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly int $tenantId
    ) {}

    public function handle(AiProfileGeneratorService $aiService): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $profile = $aiService->generateProfile($tenant);
        $tenant->update(['ai_profile' => $profile]);
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function tags(): array
    {
        return ['ai', 'profile', 'tenant:' . $this->tenantId];
    }
}
