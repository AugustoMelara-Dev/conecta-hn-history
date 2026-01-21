<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Time;

use App\Domain\Shared\Contracts\ClockInterface;
use DateTimeImmutable;

class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
