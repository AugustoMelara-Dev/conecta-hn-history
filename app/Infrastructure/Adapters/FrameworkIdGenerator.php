<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters;

use App\Application\Shared\Contracts\IdGeneratorInterface;
use Illuminate\Support\Str;

final class FrameworkIdGenerator implements IdGeneratorInterface
{
    public function generate(): string
    {
        return Str::uuid()->toString();
    }
}
