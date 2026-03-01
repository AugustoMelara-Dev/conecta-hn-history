<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\AI;

use App\Domain\Shared\Middleware\TenantQueueMiddleware;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Services\AiProfileGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Domain\Identity\Exceptions\TenantNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * GenerateBusinessProfile - AI Business Profile Generation Use Case
 *
 * APPLICATION JOB: Uses Repository pattern for entity hydration.
 */
final class GenerateBusinessProfile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $tenantId
    ) {}

    public function handle(
        TenantRepositoryInterface $tenantRepository,
        AiProfileGeneratorService $aiService
    ): void {
        $tenant = $tenantRepository->findById($this->tenantId);
        if ($tenant === null) {
            throw new TenantNotFoundException("Tenant {$this->tenantId} not found");
        }

        $profile = $aiService->generateProfile($tenant);
        $tenant->ai_profile = $profile;
        $tenantRepository->save($tenant);
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function tags(): array
    {
        return ['ai', 'profile', 'tenant:'.$this->tenantId];
    }
}
