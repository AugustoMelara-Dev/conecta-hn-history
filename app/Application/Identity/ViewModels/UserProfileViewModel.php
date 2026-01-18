<?php

declare(strict_types=1);

namespace App\Application\Identity\ViewModels;

use DateTimeImmutable;

/**
 * UserProfileViewModel - Pure Data Container (DTO)
 *
 * CHANGES (Final Hardening):
 * - Removed Carbon dependency (The No-Carbon Rule)
 * - Changed int $id to string $id (The UUID Mandate)
 * - Added string $tenantId for multi-tenant completeness
 * - All properties are now primitive scalars for direct JSON serialization
 */
final readonly class UserProfileViewModel
{
    public function __construct(
        /**
         * UUID of the user.
         * CHANGE: int → string to support UUID format.
         */
        public string $id,

        /**
         * UUID of the tenant.
         * ADDED: For multi-tenant completeness.
         */
        public string $tenantId,

        /**
         * User's display name.
         */
        public string $name,

        /**
         * User's email address.
         */
        public string $email,

        /**
         * Tenant name (nullable for orphan users).
         */
        public ?string $tenantName,

        /**
         * Tenant slug (nullable for orphan users).
         */
        public ?string $tenantSlug,

        /**
         * ISO 8601 formatted creation timestamp.
         * CHANGE: Removed Carbon, using native string.
         */
        public string $createdAt,
    ) {}

    /**
     * Map raw database row (stdClass) to ViewModel.
     * 
     * Framework-agnostic date handling using DateTimeImmutable.
     *
     * @param object $row Raw result from ConnectionInterface->table()->first()
     */
    public static function fromRow(object $row): self
    {
        // Handle date conversion without Carbon
        $createdAt = $row->created_at instanceof DateTimeImmutable
            ? $row->created_at->format(DateTimeImmutable::ATOM)
            : (new DateTimeImmutable($row->created_at))->format(DateTimeImmutable::ATOM);

        return new self(
            id: (string) ($row->uuid ?? $row->id),
            tenantId: (string) ($row->tenant_id ?? ''),
            name: (string) $row->name,
            email: (string) $row->email,
            tenantName: isset($row->tenant_name) ? (string) $row->tenant_name : null,
            tenantSlug: isset($row->tenant_slug) ? (string) $row->tenant_slug : null,
            createdAt: $createdAt,
        );
    }

    /**
     * Reconstruct from array (for cache hydration).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            tenantId: (string) ($data['tenantId'] ?? $data['tenant_id'] ?? ''),
            name: (string) $data['name'],
            email: (string) $data['email'],
            tenantName: isset($data['tenantName']) ? (string) $data['tenantName'] : null,
            tenantSlug: isset($data['tenantSlug']) ? (string) $data['tenantSlug'] : null,
            createdAt: (string) $data['createdAt'],
        );
    }

    /**
     * Serialize to array for JSON responses.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'name' => $this->name,
            'email' => $this->email,
            'tenantName' => $this->tenantName,
            'tenantSlug' => $this->tenantSlug,
            'createdAt' => $this->createdAt,
        ];
    }
}
