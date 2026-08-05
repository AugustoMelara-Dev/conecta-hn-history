<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

/**
 * LeadEntity - Pure Domain Entity for Sales Lead
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE: Zero dependencies on ORM or Infrastructure.                      ║
 * ║  IMMUTABLE: State protected via readonly properties.                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class LeadEntity
{
    /**
     * @param  array{name: ?string, phone: ?string, message: ?string, status: string, product_id: ?int}  $contactData
     */
    public function __construct(
        public int $id,
        public string $tenantId,
        public string $source,
        public array $contactData,
    ) {}
}
