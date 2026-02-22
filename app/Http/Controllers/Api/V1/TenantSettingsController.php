<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Tenant\UpdateTenantBrandingAction;
use App\Application\Tenant\Results\UpdateBrandingResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantSettingsResource;

final class TenantSettingsController extends Controller
{
    public function update(
        UpdateTenantSettingsRequest $request,
        UpdateTenantBrandingAction $action
    ): TenantSettingsResource {

        $data = UpdateBrandingResult::fromRequest($request);
        $tenant = $action->execute($data);

        return new TenantSettingsResource($tenant);
    }

    public function show(): TenantSettingsResource
    {
        $tenant = auth()->user()->tenant;

        if (! $tenant) {
            abort(403, 'No tenant associated with user.');
        }

        return new TenantSettingsResource($tenant);
    }
}
