<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Application\Tenant\Commands\EnhanceTenantProfileCommand;
use App\Domain\TenantGovernance\Exceptions\TenantDomainException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Services\AiProfileGeneratorService;

/**
 * EnhanceTenantProfileHandler - Pure Business Logic for AI Enhancement
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE: DIP + Hexagonal + Framework Agnostic          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - Orchestrates AI Enhancement interactions                             ║
 * ║  - Transport Agnostic (Can be called from API, Job, CLI)                ║
 * ║  - NO Illuminate imports (uses injected interfaces)                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EnhanceTenantProfileHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly TenantRepositoryInterface $repository,
        private readonly AiProfileGeneratorService $aiService,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Handle the enhancement process.
     */
    public function handle(EnhanceTenantProfileCommand $command): void
    {
        $this->transactionManager->transaction(function () use ($command) {
            // Phase 1: Hydration
            $tenant = $this->repository->findById($command->tenantId);

            if ($tenant === null) {
                throw new TenantDomainException(
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

            $this->logger->info('Tenant Profile Enhanced via AI', $this->sanitizePayload([
                'tenant_id' => $tenant->id,
                'ai_tags' => $enhancedData['tags'] ?? [],
            ]));
        });
    }
}
