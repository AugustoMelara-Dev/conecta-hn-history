<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Shared\Traits\LogSanitizer;
use App\Application\Tenant\Commands\EnhanceTenantProfileCommand;
use App\Domain\TenantGovernance\Exceptions\TenantDomainException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Services\AiProfileGeneratorService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EnhanceTenantProfileHandler - Pure Business Logic for AI Enhancement
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE: DIP + Hexagonal                               ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - Orchestrates AI Enhancement interactions                             ║
 * ║  - Transport Agnostic (Can be called from API, Job, CLI)                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EnhanceTenantProfileHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly TenantRepositoryInterface $repository,
        private readonly AiProfileGeneratorService $aiService,
    ) {}

    /**
     * Handle the enhancement process.
     *
     * @param EnhanceTenantProfileCommand $command
     * @return void
     */
    public function handle(EnhanceTenantProfileCommand $command): void
    {
        DB::transaction(function () use ($command) {
            // Phase 1: Hydration
            $tenant = $this->repository->findById($command->tenantId);

            if ($tenant === null) {
                throw new ModelNotFoundException(
                    "Tenant with ID {$command->tenantId} not found. Cannot enhance profile."
                );
            }

            // Phase 2: Generate AI enhancements
            $enhancedData = $this->aiService->generate(
                $tenant->name ?? '',
                $command->description ?? $tenant->description ?? '',
                ($command->tags !== []) ? implode(',', $command->tags) : ($tenant->category?->name ?? 'General')
            );

            // Phase 3: Delegate to Domain
            $tenant->applyAiEnhancement($enhancedData, allowReEnhancement: $command->isAiGenerated);

            // Phase 4: Persist
            $this->repository->save($tenant);
            
            Log::info("Tenant Profile Enhanced via AI", $this->sanitizePayload([
                'tenant_id' => $tenant->id,
                'ai_tags' => $enhancedData['tags'] ?? []
            ]));
        });
    }
}
