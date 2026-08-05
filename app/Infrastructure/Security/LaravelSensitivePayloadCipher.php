<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Shared\Contracts\SensitivePayloadCipherInterface;
use Illuminate\Contracts\Encryption\Encrypter;

final readonly class LaravelSensitivePayloadCipher implements SensitivePayloadCipherInterface
{
    public function __construct(private Encrypter $encrypter) {}

    public function encrypt(string $value): string
    {
        return $this->encrypter->encryptString($value);
    }

    public function decrypt(string $value): string
    {
        return $this->encrypter->decryptString($value);
    }
}
