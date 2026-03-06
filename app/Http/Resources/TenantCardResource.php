<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TenantCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?? 'Descubre lo que tenemos para ti.',
            'tags' => array_slice($this->tags ?? [], 0, 3),
            'banner_path' => $this->hero_image ?? $this->banner_path ?? $this->cover_path,
            'blur_hash' => $this->blur_hash,
            'brand_color' => $this->brand_color ?? '#2563EB',
            'is_pro' => (bool) $this->is_pro,
            'plan_type' => $this->plan_type ?? 'semilla',
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city->id,
                'name' => $this->city->name,
                'slug' => $this->city->slug,
            ], ['id' => 0, 'name' => 'Honduras', 'slug' => 'honduras']),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'icon' => $this->category->icon ?? '🏪',
            ], ['id' => 0, 'name' => 'General', 'slug' => 'general', 'icon' => '🏪']),
        ];
    }
}
