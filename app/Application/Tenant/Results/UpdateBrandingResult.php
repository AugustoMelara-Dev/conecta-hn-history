<?php

declare(strict_types=1);

namespace App\Application\Tenant\Results;

/**
 * UpdateBrandingResult - Pure Immutable DTO for Branding Updates
 *
 * SERIALIZABLE: Uses string paths instead of UploadedFile objects.
 * HTTP AGNOSTIC: No references to Illuminate\Http\Request.
 */
final readonly class UpdateBrandingResult
{
    public function __construct(
        public string $tenantId,
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
}
