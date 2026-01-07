<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Actions\Tenant\UpdateTenantBrandingAction;
use App\Application\DTOs\Tenant\UpdateBrandingData;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantSettingsResource;

/**
 * TenantSettingsController - Dumb HTTP Gateway
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  REFACTORED: Action Pattern Implemented                                 ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - No Storage logic (Moved to UpdateTenantBrandingAction)               ║
 * ║  - No Model hydration (Moved to UpdateBrandingData)                     ║
 * ║  - Pure Delegation to Domain Action                                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class TenantSettingsController extends Controller
{
    /**
     * Update tenant settings via Action.
     *
     * POST /api/settings/update
     */
    public function update(
        UpdateTenantSettingsRequest $request,
        UpdateTenantBrandingAction $action
    ): TenantSettingsResource {
        
        // 1. DTO Hydration (Factory)
        $data = UpdateBrandingData::fromRequest($request);

        // 2. Delegation (Execution)
        $tenant = $action->execute($data);

        // 3. Response (Standardized Resource)
        return new TenantSettingsResource($tenant);
    }

    /**
     * Get current tenant settings.
     *
     * GET /api/settings
     */
    public function show(): TenantSettingsResource
    {
        // Tenant is guaranteed by 'tenant.ownership' middleware or user context
        // But for safety in strict mode, we retrieve from user.
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            abort(403, 'No tenant associated with user.');
        }

        return new TenantSettingsResource($tenant);
    }
}
