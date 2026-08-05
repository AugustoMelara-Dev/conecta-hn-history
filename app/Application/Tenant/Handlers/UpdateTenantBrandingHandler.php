<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Application\Tenant\Commands\UpdateTenantBrandingCommand;
use App\Domain\Identity\Repositories\TenantRepositoryInterface;
use App\Domain\Shared\Contracts\MediaStorageServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * UpdateTenantBrandingHandler - Branding Assets Update Handler
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HEXAGONAL: Delegates file storage to MediaStorageServiceInterface.     ║
 * ║  HTTP AGNOSTIC: No Illuminate\Http references.                          ║
 * ║  CLEANUP: Deletes old files before storing new ones.                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class UpdateTenantBrandingHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly MediaStorageServiceInterface $mediaStorage,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Handle branding update command.
     *
     * @throws ModelNotFoundException If tenant not found
     */
    public function handle(UpdateTenantBrandingCommand $command): void
    {
        // 1. Load Tenant
        $tenant = $this->tenantRepository->findById($command->tenantId);

        if ($tenant === null) {
            throw new ModelNotFoundException("Tenant not found: {$command->tenantId}");
        }

        // 2. Handle Logo Upload (delegated to MediaStorageService)
        if ($command->logo !== null) {
            // Delete old logo if exists
            if ($tenant->logo_path) {
                $this->mediaStorage->delete($command->tenantId, $tenant->logo_path);
            }
            $tenant->logo_path = $this->mediaStorage->storeLogo($command->tenantId, $command->logo);
        }

        // 3. Handle Banner Upload (delegated to MediaStorageService)
        if ($command->banner !== null) {
            // Delete old banner if exists
            if ($tenant->cover_path) {
                $this->mediaStorage->delete($command->tenantId, $tenant->cover_path);
            }
            $tenant->cover_path = $this->mediaStorage->storeBanner($command->tenantId, $command->banner);
        }

        // 4. Update text fields (only non-null values)
        $this->updateTextFields($tenant, $command);

        // 5. Persist via Repository
        $this->tenantRepository->save($tenant);

        $this->logger->info('Tenant branding updated', $this->sanitizePayload([
            'tenant_id' => $command->tenantId,
            'logo_updated' => $command->logo !== null,
            'banner_updated' => $command->banner !== null,
        ]));
    }

    /**
     * Update text fields on tenant from command.
     */
    private function updateTextFields(object $tenant, UpdateTenantBrandingCommand $command): void
    {
        if ($command->name !== null) {
            $tenant->name = $command->name;
        }
        if ($command->description !== null) {
            $tenant->description = $command->description;
        }
        if ($command->about !== null) {
            $tenant->about = $command->about;
        }
        if ($command->phone !== null) {
            $tenant->phone = $command->phone;
        }
        if ($command->whatsapp !== null) {
            $tenant->whatsapp_number = $command->whatsapp;
        }
        if ($command->address !== null) {
            $tenant->address = $command->address;
        }
        if ($command->website !== null) {
            $tenant->website_url = $command->website;
        }
        if ($command->instagram !== null) {
            $tenant->instagram_url = $command->instagram;
        }
        if ($command->facebook !== null) {
            $tenant->facebook_url = $command->facebook;
        }
        if ($command->tiktok !== null) {
            $tenant->tiktok_url = $command->tiktok;
        }
        if ($command->primaryColor !== null) {
            $tenant->primary_color = $command->primaryColor;
        }
    }
}
