<?php

declare(strict_types=1);

namespace App\Listeners\Interactions;

use App\Domain\Contracts\AdminNotifier;
use App\Domain\Interactions\Events\InteractionRecorded;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Models\Interaction;
use App\Models\Lead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessInteractionSideEffects implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private readonly AdminNotifier $notifier
    ) {}

    public function middleware(InteractionRecorded $event): array
    {
        return [new TenantQueueMiddleware($event->tenantId)];
    }

    public function handle(InteractionRecorded $event): void
    {
        // REHYDRATION: Fetch clean state from DB enforcing Tenant Scope
        $interaction = Interaction::where('id', $event->interactionId)
            ->where('tenant_id', $event->tenantId)
            ->firstOrFail();

        if ($interaction->isHighIntent()) {
            $this->handleLeadCreation($interaction);
        }
    }

    private function handleLeadCreation(Interaction $interaction): void
    {
        // Debounce: verify if lead was already created recently for this session
        $leadExists = Lead::where('tenant_id', $interaction->tenant_id)
            ->whereDate('created_at', today())
            ->where('metadata->session_id', $interaction->session_id)
            ->exists();

        if ($leadExists) {
            return;
        }

        $lead = Lead::create([
            'tenant_id' => $interaction->tenant_id,
            'source' => $interaction->type === Interaction::TYPE_WHATSAPP_CLICK ? 'whatsapp' : 'website',
            'status' => 'new',
            'metadata' => [
                'session_id' => $interaction->session_id,
                'ip' => $interaction->ip_address,
                'product_id' => $interaction->product_id,
                'product_context' => isset($interaction->metadata['message'])
                    ? strip_tags($interaction->metadata['message'])
                    : null,
            ],
        ]);

        // Notify via decoupled interface
        $this->notifier->notifyNewLead($lead);
    }
}
