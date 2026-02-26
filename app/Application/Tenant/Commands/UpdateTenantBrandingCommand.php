<?php

declare(strict_types=1);

namespace App\Application\Tenant\Commands;

use App\Domain\Shared\DTOs\FileTransferObject;

/**
 * UpdateTenantBrandingCommand - Immutable Input for Branding Update
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HTTP AGNOSTIC: Uses FileTransferObject instead of UploadedFile.        ║
 * ║  Controller maps HTTP request to this Command before dispatching.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateTenantBrandingCommand
{
    public function __construct(
        /** Tenant ID to update */
        public string $tenantId,

        /** New logo file (null = no change) */
        public ?FileTransferObject $logo = null,

        /** New banner/cover file (null = no change) */
        public ?FileTransferObject $banner = null,

        /** Business name */
        public ?string $name = null,

        /** Business description */
        public ?string $description = null,

        /** About section text */
        public ?string $about = null,

        /** Contact phone */
        public ?string $phone = null,

        /** WhatsApp number */
        public ?string $whatsapp = null,

        /** Physical address */
        public ?string $address = null,

        /** Website URL */
        public ?string $website = null,

        /** Instagram URL */
        public ?string $instagram = null,

        /** Facebook URL */
        public ?string $facebook = null,

        /** TikTok URL */
        public ?string $tiktok = null,

        /** Primary brand color (hex) */
        public ?string $primaryColor = null,

        /** Schema Version for Upcasting */
        public int $schemaVersion = 1,
    ) {}
}
