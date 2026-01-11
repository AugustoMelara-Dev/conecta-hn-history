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
     * @param int $id
     * @param int $tenantId
     * @param string $source
     * @param array{name: string, phone: string, message: ?string, status: string, product_id: ?int} $contactData
     */
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $source,
        public array $contactData,
    ) {}
}
