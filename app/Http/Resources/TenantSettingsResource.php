<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\Tenant
 */
final class TenantSettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Tenant $tenant */
        $tenant = $this->resource;

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'slogan' => $tenant->slogan ?? null,
            'brand_color' => $tenant->brand_color ?? null,
            'logo_url' => $tenant->logo_path ? Storage::url($tenant->logo_path) : null,
            'banner_url' => $tenant->cover_path ? Storage::url($tenant->cover_path) : null,
            // Additional fields useful for settings UI
            'description' => $tenant->description,
            'whatsapp' => $tenant->whatsapp_number,
            'whatsapp_number' => $tenant->whatsapp_number,
        ];
    }
}
