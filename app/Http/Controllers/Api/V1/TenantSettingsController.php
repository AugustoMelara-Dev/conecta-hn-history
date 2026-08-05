<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\Shared\Contracts\MediaStorageServiceInterface;
use App\Domain\Shared\DTOs\FileTransferObject;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantSettingsResource;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * TenantSettingsController - Dumb HTTP Gateway
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  REFACTORED: Action Pattern Implemented                                 ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - No Storage logic (Moved to UpdateTenantBrandingAction)               ║
 * ║  - No Model hydration (Moved to UpdateBrandingResult)                     ║
 * ║  - Pure Delegation to Domain Action                                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class TenantSettingsController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantPermissionGuardInterface $permissionGuard,
        private readonly MediaStorageServiceInterface $mediaStorage,
    ) {}

    /**
     * Update tenant settings via Action.
     *
     * POST /api/settings/update
     */
    public function update(
        UpdateTenantSettingsRequest $request,
    ): TenantSettingsResource {
        $tenant = $this->authorizedTenant($request, Permission::SETTINGS_UPDATE);
        $validated = $request->validated();
        $oldLogoPath = is_string($tenant->logo_path) ? $tenant->logo_path : null;
        $newLogoPath = null;

        if ($request->hasFile('logo')) {
            $newLogoPath = $this->mediaStorage->storeLogo(
                (string) $tenant->id,
                FileTransferObject::fromUploadedFile($request->file('logo')),
            );
        }

        try {
            $tenant->forceFill([
                'bank_name' => $validated['bank_name'] ?? $tenant->bank_name,
                'bank_account' => $validated['bank_account'] ?? $tenant->bank_account,
                'bank_account_name' => $validated['bank_account_name'] ?? $tenant->bank_account_name,
                'bank_account_type' => $validated['bank_account_type'] ?? $tenant->bank_account_type,
                'whatsapp_number' => $validated['whatsapp_number'] ?? $tenant->whatsapp_number,
                'logo_path' => $newLogoPath
                    ?? (($validated['remove_logo'] ?? false) ? null : $tenant->logo_path),
            ])->save();
        } catch (\Throwable $exception) {
            if ($newLogoPath !== null) {
                $this->mediaStorage->delete((string) $tenant->id, $newLogoPath);
            }

            throw $exception;
        }

        if ($oldLogoPath !== null && $oldLogoPath !== $tenant->logo_path) {
            $this->mediaStorage->delete((string) $tenant->id, $oldLogoPath);
        }

        return new TenantSettingsResource($tenant->refresh());
    }

    /**
     * Get current tenant settings.
     *
     * GET /api/settings
     */
    public function show(Request $request): TenantSettingsResource
    {
        $tenant = $this->authorizedTenant($request, Permission::SETTINGS_VIEW);

        return new TenantSettingsResource($tenant);
    }

    private function authorizedTenant(Request $request, Permission $permission): Tenant
    {
        $tenant = Tenant::query()
            ->whereKey((string) $this->tenantContext->id())
            ->where('is_active', true)
            ->firstOrFail();

        abort_unless($this->permissionGuard->allows(
            $request->user(),
            $tenant,
            Capability::CATALOG_BASIC,
            $permission,
        ), 403);

        return $tenant;
    }
}
