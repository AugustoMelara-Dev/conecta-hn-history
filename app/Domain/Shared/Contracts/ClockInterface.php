<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
