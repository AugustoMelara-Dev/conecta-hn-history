<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Application\Tenant\Commands\UpdateTenantBrandingCommand;
use App\Domain\Shared\Contracts\MediaStorageServiceInterface;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;

/**
 * UpdateTenantBrandingHandler - Branding Assets Update Handler
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HEXAGONAL: Delegates file storage to MediaStorageServiceInterface.     ║
 * ║  HTTP AGNOSTIC: No Illuminate\Http references.                          ║
 * ║  CLEANUP: Deletes old files before storing new ones.                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateTenantBrandingHandler
{
    use LogSanitizer;

    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private MediaStorageServiceInterface $mediaStorage,
        private LoggerInterface $logger,
        private DataScrubberInterface $scrubber,
    ) {}

    /**
     * Handle branding update command.
     *
     * @throws TenantNotFoundException If tenant not found
     */
    public function handle(UpdateTenantBrandingCommand $command): void
    {
        // 1. Load Tenant
        $tenant = $this->tenantRepository->findById($command->tenantId);

        if ($tenant === null) {
            throw TenantNotFoundException::withId($command->tenantId);
        }

        // 2. Handle Logo Upload (delegated to MediaStorageService)
        if ($command->logo !== null) {
            if ($tenant->logoPath() !== null) {
                $this->mediaStorage->delete($tenant->logoPath());
            }

            $tenant->replaceLogoPath($this->mediaStorage->storeLogo($command->tenantId, $command->logo));
        }

        // 3. Handle Banner Upload (delegated to MediaStorageService)
        if ($command->banner !== null) {
            if ($tenant->coverPath() !== null) {
                $this->mediaStorage->delete($tenant->coverPath());
            }

            $tenant->replaceCoverPath($this->mediaStorage->storeBanner($command->tenantId, $command->banner));
        }

        // 4. Update text fields (only non-null values)
        $this->updateTextFields($tenant, $command);

        // 5. Persist via Repository
        $this->tenantRepository->save($tenant);

        $this->logger->info('Tenant branding updated', $this->sanitizePayload([
            'tenant_id' => $command->tenantId,
            'logo_updated' => $command->logo !== null,
            'banner_updated' => $command->banner !== null,
        ], $this->scrubber));
    }

    /**
     * Update text fields on tenant from command.
     */
    private function updateTextFields(TenantEntity $tenant, UpdateTenantBrandingCommand $command): void
    {
        $attributes = array_filter([
            'name' => $command->name,
            'description' => $command->description,
            'about' => $command->about,
            'phone' => $command->phone,
            'whatsapp' => $command->whatsapp,
            'address' => $command->address,
            'website_url' => $command->website,
            'instagram_url' => $command->instagram,
            'facebook_url' => $command->facebook,
            'tiktok_url' => $command->tiktok,
            'primary_color' => $command->primaryColor,
        ], static fn (?string $value): bool => $value !== null);

        $tenant->updateBranding($attributes);
    }
}
