<?php

namespace App\Traits;

use App\Models\Favorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * CanBeFavorited Trait
 *
 * Apply to any model that can be favorited by users.
 * Examples: Tenant, Product, Post, etc.
 *
 * Usage:
 *   class Tenant extends Model
 *   {
 *       use CanBeFavorited;
 *   }
 */
trait CanBeFavorited
{
    /**
     * Get all favorites for this model
     */
    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    /**
     * Check if this model is favorited by a specific user
     */
    public function isFavoritedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->favorites()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Get the count of users who favorited this model
     */
    public function getFavoritesCount(): int
    {
        return $this->favorites()->count();
    }

    /**
     * Add to favorites for a user
     */
    public function addToFavorites(User $user): Favorite
    {
        return $this->favorites()->firstOrCreate([
            'user_id' => $user->id,
        ]);
    }

    /**
     * Remove from favorites for a user
     */
    public function removeFromFavorites(User $user): bool
    {
        return $this->favorites()
            ->where('user_id', $user->id)
            ->delete() > 0;
    }

    /**
     * Toggle favorite status for a user
     *
     * @return bool True if now favorited, false if unfavorited
     */
    public function toggleFavorite(User $user): bool
    {
        if ($this->isFavoritedBy($user)) {
            $this->removeFromFavorites($user);

            return false;
        }

        $this->addToFavorites($user);

        return true;
    }
}

