<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Order;
use Illuminate\Support\Collection;

interface InventoryManagerInterface
{
    public function validateAndLock(array $cartItems, int $tenantId): Collection;

    public function restock(\App\Models\Order $order): void;

    public function consume(\App\Models\Order $order): void;
}
