<?php

declare(strict_types=1);

namespace App\Application\Orders;

use App\Models\Order;

final readonly class OrderWriteResult
{
    public function __construct(
        public Order $order,
        public bool $replayed = false,
    ) {}
}
