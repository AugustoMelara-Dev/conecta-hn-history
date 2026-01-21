<?php

declare(strict_types=1);

namespace App\Application\Tenant\Commands;

/**
 * EnhanceTenantProfileCommand - Input DTO for AI Profile Enhancement
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CQRS COMPLIANCE: Typed input contract for EnhanceTenantProfileHandler   ║
 * ║  IMMUTABILITY: Final readonly to prevent mutation after construction     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class EnhanceTenantProfileCommand
{
    /**
     * @param  int  $tenantId  Target tenant identifier
     * @param  string|null  $description  Updated description for AI processing
     * @param  array<int, string>  $tags  Category tags to guide AI enhancement
     * @param  bool  $isAiGenerated  Allow re-enhancement of previously AI-generated content
     */
    public function __construct(
        public int $tenantId,
        public ?string $description = null,
        public array $tags = [],
        public bool $isAiGenerated = false,
    ) {}
}
