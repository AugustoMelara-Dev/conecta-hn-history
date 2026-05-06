<?php

declare(strict_types=1);

namespace App\Application\Interactions\Handlers;

use App\Application\Interactions\Commands\RecordInteractionCommand;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Analytics\Repositories\InteractionRepositoryInterface;
use App\Domain\Sales\Repositories\LeadRepositoryInterface;
use App\Domain\Shared\Contracts\AdminNotifierInterface;

/**
 * RecordInteractionHandler - Pure Interaction Recording Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ZERO ELOQUENT: No App\Models imports - operates via interfaces only.   ║
 * ║  VISITOR-AWARE: Deduplication is per-visitor, not global.               ║
 * ║  CONVERSION: WhatsApp clicks automatically create Leads.                ║
 * ║  PURE ENTITIES: Works with domain entities throughout.                  ║
 * ║  FRAMEWORK AGNOSTIC: Uses injected interfaces for DB & Log.             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class RecordInteractionHandler
{
    use LogSanitizer;

    private const DEDUPLICATION_WINDOW_MINUTES = 5;

    public function __construct(
        private readonly InteractionRepositoryInterface $interactionRepository,
        private readonly LeadRepositoryInterface $leadRepository,
        private readonly AdminNotifierInterface $adminNotifier,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Handle interaction recording command.
     */
    public function handle(RecordInteractionCommand $command): void
    {
        $this->transactionManager->transaction(function () use ($command): void {
            // ═══════════════════════════════════════════════════════════════
            // PHASE 1: Deduplication Check (5-minute window, PER-VISITOR)
            // ═══════════════════════════════════════════════════════════════
            $isDuplicate = $this->interactionRepository->hasRecentInteraction(
                tenantId: $command->tenantId,
                visitorId: $command->visitorId,
                type: $command->type,
                withinMinutes: self::DEDUPLICATION_WINDOW_MINUTES,
            );

            if ($isDuplicate) {
                $this->logger->debug('Interaction deduplicated', $this->sanitizePayload([
                    'tenant_id' => $command->tenantId,
                    'visitor_id' => substr($command->visitorId, 0, 8).'...',
                    'type' => $command->type,
                ]));

                return;
            }

            // ═══════════════════════════════════════════════════════════════
            // PHASE 2: Record Interaction
            // ═══════════════════════════════════════════════════════════════
            $interaction = $this->interactionRepository->create(
                tenantId: $command->tenantId,
                visitorId: $command->visitorId,
                type: $command->type,
                productId: $command->productId,
                ipAddress: $command->ipAddress,
                userAgent: $command->userAgent,
                referrer: $command->referrer,
                url: $command->url,
                metadata: $command->metadata,
            );

            $this->logger->info('Interaction recorded', $this->sanitizePayload([
                'tenant_id' => $command->tenantId,
                'type' => $command->type,
                'interaction_id' => $interaction->id,
            ]));

            // ═══════════════════════════════════════════════════════════════
            // PHASE 3: Business Rule - WhatsApp Click → Lead Conversion
            // ═══════════════════════════════════════════════════════════════
            if ($this->isWhatsAppClick($command->type)) {
                $lead = $this->leadRepository->createFromInteraction(
                    tenantId: $command->tenantId,
                    productId: $command->productId,
                    source: $command->type,
                );

                $this->logger->info('Lead created from interaction', [
                    'tenant_id' => $command->tenantId,
                    'lead_id' => $lead->id,
                ]);

                $this->adminNotifier->notifyNewLead($lead);
            }
        });
    }

    /**
     * Check if interaction type is a WhatsApp click.
     */
    private function isWhatsAppClick(string $type): bool
    {
        return in_array($type, [
            'whatsapp_click',
            'whatsapp_catalog_click',
            'whatsapp_product_click',
        ], true);
    }
}
