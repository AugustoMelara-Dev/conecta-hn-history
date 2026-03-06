<?php

declare(strict_types=1);

namespace App\Infrastructure\Interactions\Actions;

use App\Application\Interactions\Commands\RecordInteractionCommand;
use App\Application\Interactions\DTOs\InteractionData;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\MetadataSanitizerInterface;
use App\Application\Shared\Contracts\TenantRateLimiterInterface;

/**
 * RecordInteractionAction - Infrastructure Adapter
 *
 * Relocated from app/Actions/Interactions to Infrastructure layer.
 * ADAPTER PATTERN: Maps InteractionData DTO → Command → CommandBus.
 * PURE DELEGATION: Zero business logic, zero persistence, zero events.
 */
final readonly class RecordInteractionAction
{
    private const MAX_METADATA_BYTES = 10240;

    public function __construct(
        private CommandBusInterface $commandBus,
        private TenantRateLimiterInterface $tenantRateLimiter,
        private MetadataSanitizerInterface $metadataSanitizer,
    ) {}

    /**
     * @throws \InvalidArgumentException If metadata exceeds MAX_METADATA_BYTES.
     * @throws \RuntimeException If tenant context is missing.
     */
    public function execute(InteractionData $data): void
    {
        if (empty($data->tenantId)) {
            throw new \RuntimeException('Cannot record interaction: missing tenant context.');
        }

        $this->tenantRateLimiter->ensureNotExceeded(
            actionName: self::class,
            tenantId: $data->tenantId,
            maxAttempts: 1000,
            decayMinutes: 1
        );

        $encodedMetadata = json_encode($data->metadata ?? []);
        if ($encodedMetadata !== false && strlen($encodedMetadata) > self::MAX_METADATA_BYTES) {
            throw new \InvalidArgumentException(
                'The metadata exceeds the maximum allowed size of 10KB.'
            );
        }

        $cleanMetadata = $this->metadataSanitizer->clean($data->metadata ?? []);

        $this->commandBus->dispatch(
            new RecordInteractionCommand(
                tenantId: $data->tenantId,
                visitorId: $data->sessionId ?? '',
                type: $data->type,
                productId: $data->productId,
                ipAddress: $data->ipAddress,
                userAgent: $data->userAgent,
                metadata: $cleanMetadata,
            )
        );
    }
}
