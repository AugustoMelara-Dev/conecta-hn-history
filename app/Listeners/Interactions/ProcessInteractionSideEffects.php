<?php

declare(strict_types=1);

namespace App\Listeners\Interactions;

use App\Domain\Contracts\AdminNotifier;
use App\Domain\Interactions\Events\InteractionRecorded;
use App\Domain\TenantGovernance\Services\OperationalPlaybookTaskManager;
use App\Models\Interaction;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessInteractionSideEffects implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private readonly AdminNotifier $notifier,
        private readonly OperationalPlaybookTaskManager $playbookTaskManager,
    ) {}

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
        $message = isset($interaction->metadata['message'])
            ? trim(strip_tags((string) $interaction->metadata['message']))
            : null;

        $leadExistsQuery = Lead::query()
            ->where('tenant_id', $interaction->tenant_id)
            ->whereDate('created_at', today());

        if (filled($interaction->session_id)) {
            $leadExistsQuery->where('session_id', $interaction->session_id);
        } elseif ($message !== null && $message !== '') {
            $leadExistsQuery
                ->where('source', $interaction->type === Interaction::TYPE_WHATSAPP_CLICK ? 'whatsapp' : 'website')
                ->where('message', $message);
        }

        $leadExists = $leadExistsQuery->exists();

        if ($leadExists) {
            return;
        }

        $lead = Lead::create([
            'tenant_id' => $interaction->tenant_id,
            'name' => $interaction->type === Interaction::TYPE_WHATSAPP_CLICK ? 'Lead desde WhatsApp' : 'Lead web',
            'product_id' => $interaction->getAttribute('product_id'),
            'source' => $interaction->type === Interaction::TYPE_WHATSAPP_CLICK ? 'whatsapp' : 'website',
            'session_id' => $interaction->session_id,
            'message' => $message,
            'status' => 'new',
        ]);

        $tenant = Tenant::query()->find((string) $interaction->tenant_id);

        if ($tenant instanceof Tenant) {
            $this->playbookTaskManager->syncForTrigger($tenant, 'lead_follow_up', [
                'related' => $lead,
                'customer_name' => (string) ($lead->name ?? 'este cliente'),
            ]);
        }

        // Notify via decoupled interface
        $this->notifier->notifyNewLead($lead);
    }
}
