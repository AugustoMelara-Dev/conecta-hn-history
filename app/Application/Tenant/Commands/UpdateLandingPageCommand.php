<?php

declare(strict_types=1);

namespace App\Application\Tenant\Commands;

/**
 * UpdateLandingPageCommand - Immutable Input for Landing Page Update
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PRO FEATURE: Handler validates subscription status before processing.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateLandingPageCommand
{
    public function __construct(
        /** Tenant ID to update */
        public int $tenantId,

        /**
         * Landing page configuration data.
         * May include: sections, colors, hero text, CTA buttons, etc.
         */
        public array $landingData,
    ) {}
}
