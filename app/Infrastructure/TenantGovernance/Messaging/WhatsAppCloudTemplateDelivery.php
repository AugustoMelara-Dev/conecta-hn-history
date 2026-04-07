<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Messaging;

use App\Domain\TenantGovernance\Contracts\WhatsAppTemplateDeliveryInterface;
use Illuminate\Http\Client\Factory as HttpFactory;

final class WhatsAppCloudTemplateDelivery implements WhatsAppTemplateDeliveryInterface
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendTemplate(string $accessToken, string $apiVersion, string $phoneNumberId, array $payload): array
    {
        return $this->http->timeout(15)
            ->acceptJson()
            ->withToken($accessToken)
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", $payload)
            ->throw()
            ->json();
    }
}
