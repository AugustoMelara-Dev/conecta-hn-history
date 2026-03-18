<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Tenant
 */
final class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->resource;

        return [
            'id' => (string) $tenant->id,
            'name' => (string) $tenant->name,
            'slug' => (string) $tenant->slug,
            'description' => (string) $tenant->description,
            'about' => (string) $tenant->about,
            'phone' => (string) $tenant->phone,
            'whatsapp_number' => (string) $tenant->whatsapp_number,
            'address' => (string) data_get($tenant, 'address', ''),
            'instagram_url' => (string) $tenant->instagram_url,
            'facebook_url' => (string) $tenant->facebook_url,
            'tiktok_url' => (string) $tenant->tiktok_url,
            'website_url' => (string) $tenant->website_url,
            'logo_url' => $tenant->logo_path ? Storage::url($tenant->logo_path) : null,
            'cover_url' => $tenant->cover_path ? Storage::url($tenant->cover_path) : null,
            'is_pro' => (bool) $tenant->is_pro,
            'is_active' => (bool) $tenant->is_active,
            'created_at' => $tenant->created_at?->toIso8601String(),
            'updated_at' => $tenant->updated_at?->toIso8601String(),
        ];
    }
}
