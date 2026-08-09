<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Queries;

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * AdminProductQuery - Centralized "Backdoor" Access
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PATTERN: Encapsulates `withoutGlobalScope` logic here.                 ║
 * ║  AUDIT: Easy to find all admin-level access points.                     ║
 * ║  SAFETY: Replaces scattered `withoutGlobalScope` calls in Controllers.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class AdminProductQuery
{
    public function __construct(
        private readonly TenantContext $context
    ) {}

    /**
     * Get all products for all tenants (Super Admin View).
     *
     * @return Collection<int, Product>
     */
    public function getAllSystemProducts(): Collection
    {
        // Enforce System Mode Requirement?
        // Or simply act as the trusted caller.

        // Option 1: Switch context temporarily (Dangerous)
        // Option 2: Use Model Bypass (Explicit Scope Removal)

        return Product::withoutGlobalScope(TenantScope::class)
            ->with(['tenant']) // Admin usually needs to know who owns what
            ->get();
    }

    /**
     * Find specific product ignoring tenant.
     */
    public function findSystemProduct(int $id): ?Product
    {
        return Product::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->first();
    }
}
