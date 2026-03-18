<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exceptions;

use RuntimeException;

final class OrderNotFoundException extends RuntimeException
{
    public static function withId(int $orderId): self
    {
        return new self("Order not found: {$orderId}");
    }
}
