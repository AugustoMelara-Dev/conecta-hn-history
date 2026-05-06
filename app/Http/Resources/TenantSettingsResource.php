<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TenantSettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'slogan' => $this->slogan ?? null, // Graceful fallback if column missing
            'brand_color' => $this->brand_color ?? null,
            'logo_url' => $this->logo_path ? Storage::url($this->logo_path) : null,
            'banner_url' => $this->cover_path ? Storage::url($this->cover_path) : null,
            // Additional fields useful for settings UI
            'description' => $this->description,
            'whatsapp' => $this->whatsapp_number, // Legacy alias
            'whatsapp_number' => $this->whatsapp_number,
        ];
    }
}
