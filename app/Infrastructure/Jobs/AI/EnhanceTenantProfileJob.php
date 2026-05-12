<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\AI;

use App\Application\Tenant\Commands\EnhanceTenantProfileCommand;
use App\Application\Tenant\Handlers\EnhanceTenantProfileHandler;
use App\Domain\Shared\Middleware\TenantQueueMiddleware;
use App\Models\Tenant;
use App\Services\AiProfileGeneratorService;
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

    public int $timeout = 120;

    public array $backoff = [30, 60, 120];

    public int $uniqueFor = 3600;

    public readonly EnhanceTenantProfileCommand $command;

    public int|string $tenantId;

    public function __construct(EnhanceTenantProfileCommand|int|string $data)
    {
        $this->command = $data instanceof EnhanceTenantProfileCommand
            ? $data
            : new EnhanceTenantProfileCommand($data);
        $this->tenantId = $this->command->tenantId;
        $this->onQueue('ai-enrichment');
    }

    public function uniqueId(): string
    {
        return 'enhance-tenant-'.$this->tenantId;
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
     */
    public function handle(
        EnhanceTenantProfileHandler|AiProfileGeneratorService $handler
    ): void {
        if ($handler instanceof EnhanceTenantProfileHandler) {
            $handler->handle($this->command);

            return;
        }

        $this->handleLegacyService($handler);
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function tags(): array
    {
        return ['ai', 'enhance', 'tenant:'.$this->tenantId];
    }

    private function handleLegacyService(AiProfileGeneratorService $service): void
    {
        $tenant = Tenant::withoutGlobalScopes()->find($this->tenantId);

        if (! $tenant || $tenant->is_ai_enhanced) {
            return;
        }

        $result = $service->generate(
            (string) $tenant->name,
            (string) ($this->command->description ?? $tenant->description ?? ''),
            (string) ($tenant->category?->name ?? 'General')
        );

        if (($result['status'] ?? null) === 'flagged') {
            $tenant->forceFill([
                'status' => 'banned',
                'is_active' => false,
                'admin_notes' => trim((string) ($tenant->admin_notes ?? '')."\nAI MODERATION: ".($result['risk_analysis'] ?? 'flagged')),
            ])->save();

            return;
        }

        $tenant->forceFill([
            'name' => $result['business_name_clean'] ?? $tenant->name,
            'slogan' => $result['tagline'] ?? $tenant->slogan,
            'description' => $result['full_description_html'] ?? ($result['short_description'] ?? $tenant->description),
            'seo_description' => $result['short_description'] ?? $tenant->seo_description,
            'tags' => $result['seo_keywords'] ?? $tenant->tags,
            'is_ai_enhanced' => true,
        ])->save();
    }
}
