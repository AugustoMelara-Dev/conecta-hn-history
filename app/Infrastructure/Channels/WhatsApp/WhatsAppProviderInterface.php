<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels\WhatsApp;

interface WhatsAppProviderInterface
{
    /** @param array<string, mixed> $payload */
    public function send(array $payload): array;
}
