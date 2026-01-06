<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

/**
 * ReviewPolicy - Tenant-Aware Authorization for Reviews
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * SECURITY: Enforces tenant ownership for customer review management
 * ══════════════════════════════════════════════════════════════════════════════
 */
class ReviewPolicy extends TenantAwarePolicy
{
    /**
     * Determine whether the user can view any reviews.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    /**
     * Determine whether the user can view the review.
     */
    public function view(User $user, Review $review): bool
    {
        return $this->ownsResource($user, $review);
    }

    /**
     * Determine whether the user can create reviews.
     *
     * Note: Reviews are typically created by customers.
     */
    public function create(User $user): bool
    {
        return true; // Any authenticated user can leave reviews
    }

    /**
     * Determine whether the user can update the review.
     *
     * Tenants can moderate reviews on their business.
     */
    public function update(User $user, Review $review): bool
    {
        return $this->ownsResource($user, $review);
    }

    /**
     * Determine whether the user can delete the review.
     */
    public function delete(User $user, Review $review): bool
    {
        return $this->ownsResource($user, $review);
    }

    /**
     * Determine whether the user can restore the review.
     */
    public function restore(User $user, Review $review): bool
    {
        return $this->ownsResource($user, $review);
    }

    /**
     * Determine whether the user can permanently delete the review.
     */
    public function forceDelete(User $user, Review $review): bool
    {
        return $this->ownsResource($user, $review) && $this->isAdmin($user);
    }
}
