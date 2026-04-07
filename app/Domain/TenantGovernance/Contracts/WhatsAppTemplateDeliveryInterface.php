<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Contracts;

interface WhatsAppTemplateDeliveryInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendTemplate(string $accessToken, string $apiVersion, string $phoneNumberId, array $payload): array;
}
