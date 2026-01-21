<?php

declare(strict_types=1);

namespace App\Actions\Interactions;

use App\Domain\Contracts\AdminNotifier;
use App\Domain\Interactions\DTOs\InteractionData;
use App\Models\Interaction;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

/**
 * RecordInteractionAction - Domain Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SINGLE RESPONSIBILITY: Record interaction & Side effects (Leads)       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This action coordinates persistence and notification logic without     ║
 * ║  context of HTTP or specific Notification libraries.                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class RecordInteractionAction
{
    public function __construct(
        private readonly AdminNotifier $notifier
    ) {}

    /**
     * Execute interaction recording.
     *
     * @return Interaction|null Returns null if duplicate (idempotency)
     */
    public function execute(InteractionData $data): ?Interaction
    {
        return DB::transaction(function () use ($data) {
            // 1. Check Idempotency (Prevent duplicate counting in session)
            if ($this->isDuplicate($data)) {
                return null;
            }

            // 2. Persist Interaction
            $interaction = Interaction::create([
                'tenant_id' => $data->tenantId,
                'type' => $data->type,
                'product_id' => $data->productId,
                'ip_address' => $data->ipAddress,
                'user_agent' => $data->userAgent,
                'session_id' => $data->sessionId,
                'metadata' => $data->metadata,
            ]);

            // 3. Side Effect: Create Lead for High-Intent Actions
            if ($this->isHighIntent($data->type)) {
                $this->handleLeadCreation($data);
            }

            return $interaction;
        });
    }

    private function isDuplicate(InteractionData $data): bool
    {
        // Simple duplicate check within same session/day/product context
        // This logic can be refined based on specific business rules
        return Interaction::where('session_id', $data->sessionId)
            ->where('tenant_id', $data->tenantId)
            ->where('type', $data->type)
            ->where('product_id', $data->productId)
            ->whereDate('created_at', today())
            ->exists();
    }

    private function isHighIntent(string $type): bool
    {
        return in_array($type, [
            Interaction::TYPE_WHATSAPP_CLICK,
            Interaction::TYPE_CALL_CLICK, // Assuming calls are also high intent
        ]);
    }

    private function handleLeadCreation(InteractionData $data): void
    {
        // Debounce: verify if lead was already created recently for this session
        $leadExists = Lead::where('tenant_id', $data->tenantId)
            ->whereDate('created_at', today())
            ->where('metadata->session_id', $data->sessionId)
            ->exists();

        if ($leadExists) {
            return;
        }

        $lead = Lead::create([
            'tenant_id' => $data->tenantId,
            'source' => $data->type === Interaction::TYPE_WHATSAPP_CLICK ? 'whatsapp' : 'website',
            'status' => 'new',
            'metadata' => [
                'session_id' => $data->sessionId,
                'ip' => $data->ipAddress,
                'product_id' => $data->productId,
                'product_context' => $data->metadata['message'] ?? null,
            ],
        ]);

        // Notify via decoupled interface
        $this->notifier->notifyNewLead($lead);
    }
}
