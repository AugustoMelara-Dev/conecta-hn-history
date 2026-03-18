<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use Illuminate\Support\Collection;

interface InventoryManagerInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     * @return Collection<int, \App\Models\Product>
     */
    public function validateAndLock(array $cartItems, int|string $tenantId): Collection;
}
