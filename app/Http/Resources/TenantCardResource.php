<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = method_exists($this->resource, 'getAttributes') ? $this->resource->getAttributes() : [];
        $planType = $attributes['plan_type'] ?? 'semilla';
        $subscriptionStatus = $attributes['subscription_status'] ?? 'active';
        $tags = $this->normalizeTags($attributes['tags'] ?? []);

        return [
            'id' => $attributes['id'] ?? $this->id,
            'name' => $attributes['name'] ?? $this->name,
            'slug' => $attributes['slug'] ?? $this->slug,
            'description' => $attributes['description'] ?? 'Descubre lo que tenemos para ti.',
            'tags' => array_slice($tags, 0, 3),
            'banner_path' => $attributes['hero_image'] ?? $attributes['banner_path'] ?? $attributes['cover_path'] ?? null,
            'blur_hash' => $attributes['blur_hash'] ?? null,
            'brand_color' => $attributes['brand_color'] ?? '#2563EB',
            'is_pro' => in_array($planType, ['pro', 'enterprise'], true)
                && ! in_array($subscriptionStatus, ['past_due', 'canceled'], true),
            'plan_type' => $planType,
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city->id,
                'name' => $this->city->name,
                'slug' => $this->city->slug,
            ], ['id' => 0, 'name' => 'Honduras', 'slug' => 'honduras']),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'icon' => $this->category->getAttributes()['icon'] ?? 'store',
            ], ['id' => 0, 'name' => 'General', 'slug' => 'general', 'icon' => 'store']),
        ];
    }

    private function normalizeTags(mixed $tags): array
    {
        if (is_array($tags)) {
            return $tags;
        }

        if (! is_string($tags) || $tags === '') {
            return [];
        }

        $decoded = json_decode($tags, true);

        return is_array($decoded) ? $decoded : [];
    }
}
