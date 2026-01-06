<?php

declare(strict_types=1);

namespace App\Application\Jobs\AI;

use App\Models\Tenant;
use App\Services\AiProfileGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use App\Domain\Shared\Middleware\TenantQueueMiddleware;

final class EnhanceTenantProfileJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $timeout = 180;
    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $tenantId
    ) {}

    public function uniqueId(): string
    {
        return 'enhance_' . $this->tenantId;
    }

    public function handle(AiProfileGeneratorService $aiService): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $aiService->enhanceProfile($tenant);
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function tags(): array
    {
        return ['ai', 'enhance', 'tenant:' . $this->tenantId];
    }
}
