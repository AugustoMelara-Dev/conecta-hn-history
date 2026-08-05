<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class AiCreditUsageThresholdNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $threshold,
        private readonly int $percentage,
        private readonly string $period,
        private readonly string $url,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, int|string> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ai_credit_usage_threshold',
            'title' => $this->threshold >= 100
                ? 'Limite mensual de IA alcanzado'
                : "Consumo de IA al {$this->threshold}%",
            'body' => $this->threshold >= 100
                ? 'La IA se pauso antes de generar cargos adicionales. Revisa tu plan o espera el reinicio mensual.'
                : 'Revisa el consumo y el saldo disponible en Suscripcion y pagos.',
            'threshold' => $this->threshold,
            'percentage' => $this->percentage,
            'billing_period' => $this->period,
            'url' => $this->url,
        ];
    }
}
