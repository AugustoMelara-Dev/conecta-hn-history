<?php

declare(strict_types=1);

namespace App\Application\Identity\ViewModels;

use Carbon\Carbon;
use stdClass;

/**
 * UserProfileViewModel - Optimized Read Contract
 * 
 * Represents the public API contract for a user profile.
 * Designed for direct serialization to JSON.
 */
final readonly class UserProfileViewModel
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $tenantName, // Nullable
        public ?string $tenantSlug, // Nullable
        public string $formattedCreatedAt,
    ) {}

    /**
     * Map raw database row (stdClass) to ViewModel (Immutable)
     * 
     * @param object $row Raw result from DB::table()->first()
     */
    public static function fromRow(object $row): self
    {
        return new self(
            id: (int) $row->id,
            name: (string) $row->name,
            email: (string) $row->email,
            // Handle NULLs from LEFT JOIN
            tenantName: isset($row->tenant_name) ? (string) $row->tenant_name : null,
            tenantSlug: isset($row->tenant_slug) ? (string) $row->tenant_slug : null,
            formattedCreatedAt: Carbon::parse($row->created_at)->toDateString(), 
        );
    }
}
