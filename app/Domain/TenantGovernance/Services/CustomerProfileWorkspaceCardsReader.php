<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;

final class CustomerProfileWorkspaceCardsReader
{
    public function __construct(
        private readonly CustomerProfileWorkspaceMetricsReader $metricsReader,
    ) {}

    /**
     * @return list<array{label:string,value:string,copy:string}>
     */
    public function cards(CustomerProfile $profile): array
    {
        $metrics = $this->metricsReader->summarize($profile);

        return [
            [
                'label' => 'Valor acumulado',
                'value' => 'L. '.number_format((float) $profile->total_spent, 2),
                'copy' => 'Ticket promedio: L. '.number_format((float) $profile->average_ticket, 2),
            ],
            [
                'label' => 'Tareas activas',
                'value' => (string) $metrics['openTasks'],
                'copy' => $metrics['breachedTasks'] > 0 ? $metrics['breachedTasks'].' vencidas por SLA.' : 'Sin vencimientos ahora.',
            ],
            [
                'label' => 'Conversaciones',
                'value' => (string) $metrics['openReplies'],
                'copy' => 'Canal preferido: '.$profile->preferred_channel_label,
            ],
            [
                'label' => 'Continuidad',
                'value' => $profile->segment_display_label,
                'copy' => 'Ultimo pedido: '.$metrics['lastOrder'].' / Ultima cita: '.$metrics['lastAppointment'],
            ],
        ];
    }
}
