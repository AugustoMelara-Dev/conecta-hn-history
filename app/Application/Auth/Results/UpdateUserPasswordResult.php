<?php

declare(strict_types=1);

namespace App\Application\Auth\Results;

final readonly class UpdateUserPasswordResult
{
    public function __construct(
        public string $status,
        public string $userId
    ) {}
}
