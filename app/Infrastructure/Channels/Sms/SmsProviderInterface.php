<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels\Sms;

interface SmsProviderInterface
{
    /** @param array<string, mixed> $payload */
    public function send(array $payload): array;
}
