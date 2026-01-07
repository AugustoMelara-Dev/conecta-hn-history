<?php

declare(strict_types=1);

namespace App\Application\DTOs\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

readonly class UpdateBrandingData
{
    public function __construct(
        public int $tenantId,
        public ?UploadedFile $logo,
        public ?UploadedFile $banner, // Using 'cover' as banner based on controller context
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

    public static function fromRequest(Request $request): self
    {
        // Assuming validation is handled by FormRequest before this
        return new self(
            tenantId: $request->user()->tenant->id,
            logo: $request->file('logo'),
            banner: $request->file('cover'), // Map 'cover' input to banner property
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
