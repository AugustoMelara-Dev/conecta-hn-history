<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Security\Contracts\HasherInterface;
use Illuminate\Support\Facades\Hash;

final readonly class LaravelHasher implements HasherInterface
{
    public function make(string $value, array $options = []): string
    {
        return Hash::make($value, $options);
    }

    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        return Hash::check($value, $hashedValue, $options);
    }
}
