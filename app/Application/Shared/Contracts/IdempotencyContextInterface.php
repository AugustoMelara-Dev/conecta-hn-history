<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

interface IdempotencyContextInterface
{
public function key(): string;
}
