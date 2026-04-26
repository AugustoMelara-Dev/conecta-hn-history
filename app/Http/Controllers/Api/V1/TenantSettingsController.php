<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Tenant\Commands\UpdateTenantBrandingCommand;
use App\Application\Tenant\Handlers\UpdateTenantBrandingHandler;
use App\Domain\Shared\DTOs\FileTransferObject;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantSettingsResource;
use App\Support\Tenancy\RequestTenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        UpdateTenantBrandingHandler $handler
    ): TenantSettingsResource {
        $tenant = $request->tenantForRequest();
        $validated = $request->validated();

        if ($request->boolean('remove_logo') && is_string($tenant->logo_path) && $tenant->logo_path !== '') {
            Storage::disk('public')->delete($tenant->logo_path);
            $tenant->forceFill(['logo_path' => null])->save();
        }

        if ($request->file('logo') !== null || array_key_exists('whatsapp_number', $validated)) {
            $handler->handle(new UpdateTenantBrandingCommand(
                tenantId: (string) $tenant->getKey(),
                logo: $request->file('logo') !== null
                    ? FileTransferObject::fromUploadedFile($request->file('logo'))
                    : null,
                whatsapp: isset($validated['whatsapp_number']) ? (string) $validated['whatsapp_number'] : null,
            ));

            $tenant->refresh();
        }

        $tenant->forceFill([
            'bank_name' => $validated['bank_name'] ?? $tenant->bank_name,
            'bank_account' => $validated['bank_account'] ?? $tenant->bank_account,
            'bank_account_name' => $validated['bank_account_name'] ?? $tenant->bank_account_name,
            'bank_account_type' => $validated['bank_account_type'] ?? $tenant->bank_account_type,
            'whatsapp_number' => $validated['whatsapp_number'] ?? $tenant->whatsapp_number,
        ])->save();

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
        return new TenantSettingsResource(app(RequestTenantResolver::class)->resolve($request));
    }
}
