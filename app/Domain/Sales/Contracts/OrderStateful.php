<?php

declare(strict_types=1);

namespace App\Domain\Sales\Contracts;

interface OrderStateful
{
    public function currentStatus(): string;
}
