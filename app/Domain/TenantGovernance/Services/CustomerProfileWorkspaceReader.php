<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Models\CustomerProfile;

final class CustomerProfileWorkspaceReader
{
    public function __construct(
        private readonly CustomerProfileRepositoryInterface $customerProfiles,
        private readonly CustomerProfileWorkspaceMetricsReader $metricsReader,
    ) {}

    /**
     * @return list<array{label:string,value:string,copy:string}>
     */
    public function workspaceCards(CustomerProfile $profile): array
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

    /**
     * @return array<int, string>
     */
    public function duplicateOptions(CustomerProfile $current, string $search): array
    {
        return $this->customerProfiles
            ->searchDuplicates((string) $current->tenant_id, (string) $current->getKey(), $search)
            ->mapWithKeys(fn (CustomerProfile $profile): array => [(int) $profile->getKey() => $this->formatDuplicateLabel($profile)])
            ->all();
    }

    public function duplicateLabel(CustomerProfile $current, mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $profile = $this->customerProfiles->findTenantRecord((string) $current->tenant_id, (int) $value);

        return $profile instanceof CustomerProfile ? $this->formatDuplicateLabel($profile) : null;
    }

    private function formatDuplicateLabel(CustomerProfile $profile): string
    {
        return trim(implode(' | ', array_filter([
            '#'.$profile->getKey(),
            $profile->name,
            $profile->email,
            $profile->phone,
            $profile->last_seen_at?->diffForHumans(),
        ])));
    }
}
