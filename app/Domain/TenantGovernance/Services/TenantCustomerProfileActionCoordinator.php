<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;

final class TenantCustomerProfileActionCoordinator
{
    public function __construct(
        private readonly CustomerLoyaltyManager $loyaltyManager,
        private readonly CustomerAutomationManager $automationManager,
        private readonly CustomerLifecycleTriggerManager $lifecycleTriggerManager,
    ) {}

    public function awardBonus(CustomerProfile $profile, array $data, ?string $actorUserId = null): array
    {
        $this->loyaltyManager->awardBonus($profile, (int) $data['points'], (string) $data['description'], $actorUserId);

        return ['title' => 'Bonificacion aplicada', 'color' => 'success', 'body' => 'El cliente ya tiene puntos nuevos y el CRM lo reflejo en su ficha.'];
    }

    public function redeemReward(CustomerProfile $profile, array $data, ?string $actorUserId = null): array
    {
        $this->loyaltyManager->redeemReward($profile, (int) $data['reward_id'], $actorUserId);

        return ['title' => 'Canje registrado', 'color' => 'success', 'body' => 'La recompensa y el balance del cliente ya quedaron actualizados.'];
    }

    public function sendEmailFollowUp(CustomerProfile $profile, array $data, ?string $actorUserId = null): array
    {
        $touchpoint = $this->automationManager->sendEmailTouchpoint(
            profile: $profile,
            subject: (string) $data['subject'],
            body: (string) $data['body'],
            automationKey: (string) ($data['automation_key'] ?? 'manual_follow_up'),
            actorUserId: $actorUserId,
        );

        return $this->touchpointFeedback($touchpoint, 'El CRM ya guardo este movimiento en el historial del cliente.');
    }

    public function sendWhatsappFollowUp(CustomerProfile $profile, ?string $actorUserId = null): array
    {
        $touchpoint = $this->automationManager->sendSuggestedAutomation($profile, 'manual_follow_up_whatsapp', $actorUserId, 'whatsapp');

        return $this->touchpointFeedback($touchpoint, 'El seguimiento por WhatsApp ya quedo auditado en el CRM.');
    }

    public function triggerReactivation(CustomerProfile $profile, ?string $actorUserId = null): array
    {
        $touchpoint = $this->lifecycleTriggerManager->triggerJourney($profile, 'reactivation', $actorUserId);

        return $this->touchpointFeedback($touchpoint, 'El CRM ya registro la accion de reactivacion para este cliente.', true);
    }

    private function touchpointFeedback(?CustomerTouchpoint $touchpoint, string $defaultBody, bool $reactivation = false): array
    {
        return [
            'title' => match ($touchpoint?->status) {
                CustomerTouchpoint::STATUS_SENT => $reactivation ? 'Reactivacion enviada' : 'Seguimiento enviado',
                CustomerTouchpoint::STATUS_SKIPPED => $reactivation ? 'Reactivacion omitida' : 'Contacto omitido',
                CustomerTouchpoint::STATUS_FAILED => 'No se pudo enviar',
                default => $reactivation ? 'Reactivacion procesada' : 'Contacto registrado',
            },
            'color' => match ($touchpoint?->status) {
                CustomerTouchpoint::STATUS_SENT => 'success',
                CustomerTouchpoint::STATUS_SKIPPED => 'warning',
                CustomerTouchpoint::STATUS_FAILED => 'danger',
                default => 'info',
            },
            'body' => (string) ($touchpoint?->last_error ?: $defaultBody),
        ];
    }
}
