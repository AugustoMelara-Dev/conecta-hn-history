<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerTouchpointEventRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\CustomerTouchpointRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\InboundWebhookRepositoryInterface;

final class WhatsAppTouchpointEventProcessor
{
    public function __construct(
        private readonly WhatsAppTouchpointStatusProcessor $statusProcessor,
        private readonly WhatsAppInboundMessageProcessor $inboundMessageProcessor,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $statusPayload
     * @param  array<string, string>  $headers
     */
    public function recordStatus(array $value, array $statusPayload, array $headers): bool
    {
        return $this->statusProcessor->record($value, $statusPayload, $headers);
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $messagePayload
     * @param  array<string, string>  $headers
     */
    public function recordInboundMessage(array $value, array $messagePayload, array $headers): bool
    {
        return $this->inboundMessageProcessor->record($value, $messagePayload, $headers);
    }
}
