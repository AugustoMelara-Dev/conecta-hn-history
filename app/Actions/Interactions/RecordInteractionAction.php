<?php

declare(strict_types=1);

namespace App\Actions\Interactions;

use App\Application\Interactions\Commands\RecordInteractionCommand;
use App\Application\Interactions\DTOs\InteractionData;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\MetadataSanitizerInterface;
use App\Application\Shared\Contracts\TenantRateLimiterInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * RecordInteractionAction - High-Throughput Analytics Adapter
 *
 * Validates tenant context, caps the 'metadata' payload at 10240 bytes,
 * sanitizes it, and forwards the result to the CommandBusInterface.
 */
final readonly class RecordInteractionAction
{
    private const MAX_METADATA_BYTES = 10240;

    public function __construct(
        private CommandBusInterface $commandBus,
        private TenantRateLimiterInterface $tenantRateLimiter,
        private MetadataSanitizerInterface $metadataSanitizer,
    ) {}

    public function execute(InteractionData $data): void
    {
        if (empty($data->tenantId)) {
            throw new RuntimeException('Cannot record interaction: missing tenant context.');
        }

        $this->tenantRateLimiter->ensureNotExceeded(
            actionName: self::class,
            tenantId: $data->tenantId,
            maxAttempts: 1000,
            decayMinutes: 1,
        );

        $encoded = json_encode($data->metadata ?? []);
        if ($encoded !== false && strlen($encoded) > self::MAX_METADATA_BYTES) {
            throw new InvalidArgumentException(
                "Interaction 'metadata' payload exceeds 10240 bytes. Reduce the payload before submitting."
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
