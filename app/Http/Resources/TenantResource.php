<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * TenantResource - Data Sovereignty Layer
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  STRICT TRANSFORMATION LAYER                                             ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  1. ID Shielding: Maps internal ID to public UUID (security).           ║
 * ║  2. ISO 8601 Standard: Dates are strictly formatted.                     ║
 * ║  3. Type Safety: Booleans and strings are explicitly cast.               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @mixin \App\Models\Tenant
 */
final class TenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // SECURITY: Hide Auto-Increment ID
            'id' => $this->uuid, // Map public ID field to internal UUID
            
            'name' => (string) $this->name,
            'slug' => (string) $this->slug,
            'description' => (string) $this->description,
            'about' => (string) $this->about,
            
            // Contact Information
            'phone' => (string) $this->phone,
            'whatsapp_number' => (string) $this->whatsapp_number,
            'address' => (string) $this->address,
            
            // Social Media
            'instagram_url' => (string) $this->instagram_url,
            'facebook_url' => (string) $this->facebook_url,
            'tiktok_url' => (string) $this->tiktok_url,
            'website_url' => (string) $this->website_url,
            
            // Assets
            'logo_url' => $this->logo_path ? Storage::url($this->logo_path) : null,
            'cover_url' => $this->cover_path ? Storage::url($this->cover_path) : null,
            
            // State
            'is_pro' => (bool) $this->is_pro,
            'is_active' => (bool) $this->is_active,
            
            // Timestamps (ISO 8601)
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
