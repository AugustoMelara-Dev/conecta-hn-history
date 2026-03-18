<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\AI;

use App\Application\Shared\Contracts\AiProfileGeneratorInterface;
use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Queue\Middleware\TenantQueueMiddleware;
use App\Services\AiProfileGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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
        public readonly string $tenantId,
        public readonly string $businessName = '',
        public readonly string $description = '',
    ) {}

    public function handle(
        TenantRepositoryInterface $tenantRepository,
        AiProfileGeneratorInterface $aiService
    ): void {
        $tenant = $tenantRepository->findById($this->tenantId);
        if ($tenant === null) {
            throw TenantNotFoundException::withId($this->tenantId);
        }

        $businessName = $this->businessName !== '' ? $this->businessName : $tenant->name();
        $description = $this->description !== '' ? $this->description : ($tenant->description() ?? '');
        $profile = $aiService->generate($businessName, $description, 'General');
        $tenant->applyAiEnhancement($profile, allowReEnhancement: true);

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
