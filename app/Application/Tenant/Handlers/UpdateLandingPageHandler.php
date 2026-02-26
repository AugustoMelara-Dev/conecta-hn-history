<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Application\Tenant\Commands\UpdateLandingPageCommand;
use App\Domain\Identity\Repositories\TenantRepositoryInterface;
use App\Domain\Tenant\Exceptions\FeatureRequiresProException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * UpdateLandingPageHandler - Landing Page Configuration Handler
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FEATURE GATING: Only PRO tenants can customize landing pages.          ║
 * ║  HTTP AGNOSTIC: No Illuminate\Http references.                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateLandingPageHandler
{
    use LogSanitizer;

    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private LoggerInterface $logger,
        private DataScrubberInterface $scrubber,
    ) {}

    /**
     * Handle landing page update command.
     *
     * @throws ModelNotFoundException If tenant not found
     * @throws FeatureRequiresProException If tenant is not PRO
     */
    public function handle(UpdateLandingPageCommand $command): void
    {
        // 1. Load Tenant
        $tenant = $this->tenantRepository->findById($command->tenantId);

        if ($tenant === null) {
            throw new ModelNotFoundException("Tenant not found: {$command->tenantId}");
        }

        // 2. FEATURE GATING: PRO subscription required
        if (! $tenant->is_pro) {
            throw FeatureRequiresProException::forLandingPage();
        }

        // 3. Update landing page data (JSON field)
        $tenant->landing_page_data = [
            'sections' => $command->landingData,
            'updated_at' => \Carbon\CarbonImmutable::now()->toIso8601String(),
        ];

        // 4. Persist via Repository
        $this->tenantRepository->save($tenant);

        $this->logger->info('Tenant landing page updated', $this->sanitizePayload([
            'tenant_id' => $command->tenantId,
        ], $this->scrubber));
    }
}
