<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\AI;

use App\Application\Tenant\Commands\EnhanceTenantProfileCommand;
use App\Application\Tenant\Handlers\EnhanceTenantProfileHandler;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * EnhanceTenantProfileJob - AI Profile Enhancement Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL ROLE: Application Service (Use Case Orchestrator)        ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  This Job is NOT responsible for business logic. It orchestrates:       ║
 * ║  1. Transaction boundaries (DB::transaction)                            ║
 * ║  2. Entity hydration (via Repository)                                   ║
 * ║  3. Delegation to Domain behavior (applyAiEnhancement)                  ║
 * ║  4. Persistence coordination (Repository::save)                         ║
 * ║                                                                          ║
 * ║  The actual business rules (validation, invariants) live in the         ║
 * ║  Tenant::applyAiEnhancement() method. This Job is a thin wrapper.       ║
 * ║                                                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class EnhanceTenantProfileJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly EnhanceTenantProfileCommand $data
    ) {}

    public function uniqueId(): string
    {
        return 'enhance_'.$this->data->tenantId;
    }

    /**
     * Execute the job.
     *
     * ORCHESTRATION PATTERN:
     * 1. Hydrate entity from repository
     * 2. Generate AI enhancements via external service
     * 3. Delegate mutation to domain entity (validates invariants)
     * 4. Persist via repository (dispatches domain events)
     *
     * All wrapped in a transaction for atomicity.
     *
     * @throws ModelNotFoundException if tenant does not exist
     * @throws TenantDomainException if business invariants are violated
     */
    public function handle(
        EnhanceTenantProfileHandler $handler
    ): void {
        $handler->handle($this->data);
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->data->tenantId)];
    }

    public function tags(): array
    {
        return ['ai', 'enhance', 'tenant:'.$this->data->tenantId];
    }
}
