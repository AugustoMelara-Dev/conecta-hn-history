<?php

declare(strict_types=1);

namespace App\Livewire\Components;

use App\Models\Favorite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * FavoriteButton - Reactive Favorite Toggle with Auth Interception
 *
 * Features:
 * - Optimistic UI (instant visual feedback)
 * - Guest interception (dispatches open-auth-modal)
 * - Auto-refresh on auth-success event
 * - Polymorphic (works with any model using CanBeFavorited trait)
 */
class FavoriteButton extends Component
{
    // The model to favorite (Tenant, Product, etc.)
    public Model $model;

    // Current favorite state
    public bool $isFavorite = false;

    // Total favorites count
    public int $count = 0;

    // Size variant
    public string $size = 'md'; // 'sm', 'md', 'lg'

    // Show count
    public bool $showCount = true;

    /**
     * Mount the component
     */
    public function mount(Model $model, string $size = 'md', bool $showCount = true): void
    {
        $this->model = $model;
        $this->size = $size;
        $this->showCount = $showCount;

        $this->refreshState();
    }

    /**
     * Refresh favorite state from database
     */
    protected function refreshState(): void
    {
        // Get favorites count
        if (method_exists($this->model, 'getFavoritesCount')) {
            $this->count = $this->model->getFavoritesCount();
        } else {
            $this->count = Favorite::where('favoritable_type', get_class($this->model))
                ->where('favoritable_id', $this->model->id)
                ->count();
        }

        // Check if current user has favorited
        if (Auth::check()) {
            if (method_exists($this->model, 'isFavoritedBy')) {
                $this->isFavorite = $this->model->isFavoritedBy(Auth::user());
            } else {
                $this->isFavorite = Favorite::where('user_id', Auth::id())
                    ->where('favoritable_type', get_class($this->model))
                    ->where('favoritable_id', $this->model->id)
                    ->exists();
            }
        } else {
            $this->isFavorite = false;
        }
    }

    /**
     * Toggle favorite status
     */
    public function toggle(): void
    {
        // Backend guard: must be authenticated
        if (! Auth::check()) {
            return; // Silently fail (frontend handles the modal)
        }

        $user = Auth::user();

        // Use trait method if available
        if (method_exists($this->model, 'toggleFavorite')) {
            $this->isFavorite = $this->model->toggleFavorite($user);
        } else {
            // Manual toggle
            $existing = Favorite::where('user_id', $user->id)
                ->where('favoritable_type', get_class($this->model))
                ->where('favoritable_id', $this->model->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $this->isFavorite = false;
            } else {
                Favorite::create([
                    'user_id' => $user->id,
                    'favoritable_type' => get_class($this->model),
                    'favoritable_id' => $this->model->id,
                ]);
                $this->isFavorite = true;
            }
        }

        // Update count
        $this->count = $this->isFavorite ? $this->count + 1 : max(0, $this->count - 1);
    }

    /**
     * Listen for auth-success event to refresh state
     * When user logs in via modal, this updates the button
     */
    #[On('auth-success')]
    public function refreshAuth(): void
    {
        $this->refreshState();
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.components.favorite-button');
    }
}
