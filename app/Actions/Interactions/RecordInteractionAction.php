<?php

declare(strict_types=1);

namespace App\Actions\Interactions;

use App\Application\Interactions\Commands\RecordInteractionCommand;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Interactions\DTOs\InteractionData;
use App\Infrastructure\Security\MetadataSanitizer;

/**
 * RecordInteractionAction - Infrastructure Adapter
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ADAPTER PATTERN: Maps InteractionData DTO → Command → CommandBus.     ║
 * ║  PURE DELEGATION: Zero business logic, zero persistence, zero events.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * REFACTORED: Extracted all orchestration logic to RecordInteractionHandler.
 * Deduplication, persistence, lead conversion, and event dispatch now live
 * in the Application Layer handler.
 */
final readonly class RecordInteractionAction
{
    /** Maximum metadata payload size in bytes (10KB). */
    private const MAX_METADATA_BYTES = 10240;

    public function __construct(
        private CommandBusInterface $commandBus,
        private \App\Application\Shared\Contracts\TenantRateLimiterInterface $tenantRateLimiter,
    ) {}

    /**
     * Dispatch interaction recording command.
     *
     * @throws \InvalidArgumentException If metadata exceeds MAX_METADATA_BYTES.
     */
    public function execute(InteractionData $data): void
    {
        if (empty($data->tenantId)) {
            throw new \RuntimeException('Cannot record interaction: missing tenant context.');
        }

        // RATE LIMITING: Tenant-Aware Protection (1000 requests per minute per tenant)
        $this->tenantRateLimiter->ensureNotExceeded(
            actionName: self::class,
            tenantId: $data->tenantId,
            maxAttempts: 1000,
            decayMinutes: 1
        );

        // RISK MITIGATION: Cap 'metadata' payload to prevent Queue/DB DoS.
        $encodedMetadata = json_encode($data->metadata ?? []);
        if ($encodedMetadata !== false && strlen($encodedMetadata) > self::MAX_METADATA_BYTES) {
            throw new \InvalidArgumentException(
                'The metadata exceeds the maximum allowed size of 10KB.'
            );
        }

        // SECURITY: Sanitize metadata to neutralize XSS payloads, depth bombs, and oversized strings.
        $cleanMetadata = MetadataSanitizer::clean($data->metadata ?? []);

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
