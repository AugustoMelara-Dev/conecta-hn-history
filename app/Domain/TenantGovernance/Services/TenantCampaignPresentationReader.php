<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\Marketing\Enums\AudienceType;
use App\Models\Campaign;

final class TenantCampaignPresentationReader
{
    /**
     * @return array<string, string>
     */
    public function channelOptions(): array
    {
        return [
            'email' => 'Email',
            'whatsapp' => 'WhatsApp',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function audienceTypeOptions(): array
    {
        return collect(AudienceType::cases())
            ->mapWithKeys(fn (AudienceType $type) => [$type->value => $type->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function customerSegmentOptions(): array
    {
        return [
            'vip' => 'VIP',
            'repeat' => 'Recurrente',
            'reactivation' => 'Reactivacion',
            'booked_service' => 'Con citas',
            'active' => 'Activo',
            'prospect' => 'Prospecto',
            'new' => 'Nuevo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function loyaltyTierOptions(): array
    {
        return [
            'vip' => 'VIP',
            'gold' => 'Gold',
            'silver' => 'Silver',
            'base' => 'Base',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function cadenceIntervalOptions(): array
    {
        return [
            1 => 'Diaria',
            3 => 'Cada 3 dias',
            7 => 'Semanal',
            14 => 'Quincenal',
            30 => 'Mensual',
        ];
    }

    public function cadencePlaybookDescription(): string
    {
        return 'Usa estas cadencias como punto de partida para campanas de recompra, reactivacion o upsell.';
    }

    public function audienceSummary(Campaign $campaign): string
    {
        return $campaign->audience_summary;
    }

    public function typeLabel(Campaign $campaign): string
    {
        return $campaign->type_label;
    }

    public function canDispatch(Campaign $campaign): bool
    {
        return $campaign->status !== 'sending';
    }

    public function dispatchLabel(Campaign $campaign): string
    {
        return $campaign->status === 'scheduled' ? 'Lanzar ahora' : 'Enviar';
    }

    public function dispatchDescription(Campaign $campaign): string
    {
        return $campaign->usesCadence()
            ? 'Se lanzara ahora y, como tiene cadencia activa, seguira corriendo con la repeticion configurada.'
            : 'Se enviara esta campana a la audiencia filtrada. Continuamos?';
    }

    public function typeTone(Campaign $campaign): string
    {
        return $campaign->type === 'whatsapp' ? 'success' : 'info';
    }

    public function cadenceTone(Campaign $campaign): string
    {
        return $campaign->usesCadence() ? 'warning' : ($campaign->scheduled_at ? 'info' : 'gray');
    }

    public function recipientsCopy(?Campaign $campaign): string
    {
        return $campaign ? $campaign->recipients_count.' contactos' : 'Se calculara al enviar';
    }

    public function sentAtCopy(?Campaign $campaign): string
    {
        return $campaign && $campaign->sent_at ? $campaign->sent_at->format('d/m/Y H:i') : 'Aun no enviada';
    }

    public function nextRunCopy(?Campaign $campaign): string
    {
        return $campaign && $campaign->next_run_at ? $campaign->next_run_at->format('d/m/Y H:i') : 'Sin agenda activa';
    }
}
