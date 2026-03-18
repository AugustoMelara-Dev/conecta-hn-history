<?php

declare(strict_types=1);

namespace App\Infrastructure\Public;

use App\Domain\Public\Contracts\BuyerRepository;
use App\Models\User;

/**
 * EloquentBuyerRepository - Implementation
 */
final class EloquentBuyerRepository implements BuyerRepository
{
    public function getFavorites(string|int $userId): array
    {
        return (User::find($userId)
            ?->favorites()
            ->with(['product.tenant', 'product.category'])
            ->get() ?? collect())->toArray();
    }

    public function getFollowedTenants(string|int $userId): array
    {
        return (User::find($userId)
            ?->following()
            ->get() ?? collect())->toArray();
    }
}
