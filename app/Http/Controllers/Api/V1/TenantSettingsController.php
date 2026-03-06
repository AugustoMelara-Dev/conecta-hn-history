<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Tenant\UpdateTenantBrandingAction;
use App\Application\Tenant\Results\UpdateBrandingResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantSettingsResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

final class TenantSettingsController extends Controller
{
    #[OA\Put(
        path: '/api/tenant/settings',
        summary: 'Update tenant branding settings',
        tags: ['Tenant'],
        requestBody: new OA\RequestBody(required: true, description: 'Settings update payload'),
        responses: [
            new OA\Response(response: 200, description: 'Settings updated successfully'),
        ]
    )]
    public function update(
        UpdateTenantSettingsRequest $request,
        UpdateTenantBrandingAction $action
    ): TenantSettingsResource {

        $data = UpdateBrandingResult::fromRequest($request);
        $tenant = $action->execute($data);

        return new TenantSettingsResource($tenant);
    }

    #[OA\Get(
        path: '/api/tenant/settings',
        summary: 'Get tenant settings',
        tags: ['Tenant'],
        responses: [
            new OA\Response(response: 200, description: 'Tenant settings'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function show(Request $request): TenantSettingsResource
    {
        $tenant = $request->user()?->tenant;

        if (! $tenant) {
            abort(403, 'No tenant associated with user.');
        }

        return new TenantSettingsResource($tenant);
    }
}
