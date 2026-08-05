<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

interface SensitivePayloadCipherInterface
{
    public function encrypt(string $value): string;

    public function decrypt(string $value): string;
}
