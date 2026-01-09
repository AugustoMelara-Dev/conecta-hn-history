<?php

declare(strict_types=1);

namespace App\Infrastructure\Public;

use App\Domain\Public\Contracts\BuyerRepository;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * EloquentBuyerRepository - Implementation
 */
final class EloquentBuyerRepository implements BuyerRepository
{
    public function getFavorites(int $userId): Collection
    {
        return User::find($userId)
            ?->favorites()
            ->with(['product.tenant', 'product.category'])
            ->get() ?? collect();
    }

    public function getFollowedTenants(int $userId): Collection
    {
        return User::find($userId)
            ?->following()
            ->get() ?? collect();
    }
}
