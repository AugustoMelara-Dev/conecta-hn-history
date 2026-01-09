<?php

declare(strict_types=1);

namespace App\Application\Tenant\Results;

use Illuminate\Http\Request;

/**
 * UpdateBrandingResult - Pure Immutable DTO for Branding Updates
 *
 * SERIALIZABLE: Uses string paths instead of UploadedFile objects.
 * Controller must pre-process file uploads before instantiating this DTO.
 */
final readonly class UpdateBrandingResult
{
    public function __construct(
        public int $tenantId,
        public ?string $logoPath,
        public ?string $bannerPath,
        public ?string $name,
        public ?string $description,
        public ?string $about,
        public ?string $phone,
        public ?string $whatsapp,
        public ?string $address,
        public ?string $website,
        public ?string $instagram,
        public ?string $facebook,
        public ?string $tiktok,
    ) {}

    /**
     * Factory method to create DTO from validated request.
     * Expects file paths to be pre-processed by controller.
     */
    public static function fromRequest(Request $request, ?string $logoPath = null, ?string $bannerPath = null): self
    {
        return new self(
            tenantId: $request->user()->tenant->id,
            logoPath: $logoPath,
            bannerPath: $bannerPath,
            name: $request->input('name'),
            description: $request->input('description'),
            about: $request->input('about'),
            phone: $request->input('phone'),
            whatsapp: $request->input('whatsapp_number'),
            address: $request->input('address'),
            website: $request->input('website_url'),
            instagram: $request->input('instagram_url'),
            facebook: $request->input('facebook_url'),
            tiktok: $request->input('tiktok_url'),
        );
    }
}
