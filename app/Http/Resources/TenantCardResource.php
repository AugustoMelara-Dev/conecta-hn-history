<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
final class TenantCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->resource;

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'description' => $tenant->description ?? 'Descubre lo que tenemos para ti.',
            'tags' => array_slice($tenant->tags ?? [], 0, 3),
            'banner_path' => $tenant->hero_image ?? $tenant->banner_path ?? $tenant->cover_path,
            'blur_hash' => $tenant->blur_hash,
            'brand_color' => $tenant->brand_color ?? '#2563EB',
            'is_pro' => (bool) $tenant->is_pro,
            'plan_type' => $tenant->plan_type ?? 'semilla',
            'city' => $this->whenLoaded('city', fn (): array => [
                'id' => data_get($tenant, 'city.id', 0),
                'name' => data_get($tenant, 'city.name', 'Honduras'),
                'slug' => data_get($tenant, 'city.slug', 'honduras'),
            ], ['id' => 0, 'name' => 'Honduras', 'slug' => 'honduras']),
            'category' => $this->whenLoaded('category', fn (): array => [
                'id' => data_get($tenant, 'category.id', 0),
                'name' => data_get($tenant, 'category.name', 'General'),
                'slug' => data_get($tenant, 'category.slug', 'general'),
                'icon' => data_get($tenant, 'category.icon', 'store'),
            ], ['id' => 0, 'name' => 'General', 'slug' => 'general', 'icon' => 'store']),
        ];
    }
}
