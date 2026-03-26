<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Models\CustomerProfile;

final class CustomerProfileWorkspaceReader
{
    public function __construct(
        private readonly CustomerProfileRepositoryInterface $customerProfiles,
    ) {}

    /**
     * @return list<array{label:string,value:string,copy:string}>
     */
    public function workspaceCards(CustomerProfile $profile): array
    {
        $openTasks = $profile->tasks()
            ->withoutGlobalScopes()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();
        $breachedTasks = $profile->tasks()
            ->withoutGlobalScopes()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->count();
        $openReplies = $profile->notes()
            ->withoutGlobalScopes()
            ->where('requires_follow_up', true)
            ->whereIn('status', ['open', 'in_progress', 'pending_customer'])
            ->count();
        $lastOrder = $profile->last_order_at?->diffForHumans() ?? 'Sin pedidos';
        $lastAppointment = $profile->last_appointment_at?->diffForHumans() ?? 'Sin citas';

        return [
            [
                'label' => 'Valor acumulado',
                'value' => 'L. '.number_format((float) $profile->total_spent, 2),
                'copy' => 'Ticket promedio: L. '.number_format((float) $profile->average_ticket, 2),
            ],
            [
                'label' => 'Tareas activas',
                'value' => (string) $openTasks,
                'copy' => $breachedTasks > 0 ? $breachedTasks.' vencidas por SLA.' : 'Sin vencimientos ahora.',
            ],
            [
                'label' => 'Conversaciones',
                'value' => (string) $openReplies,
                'copy' => 'Canal preferido: '.$profile->preferred_channel_label,
            ],
            [
                'label' => 'Continuidad',
                'value' => $profile->segment_display_label,
                'copy' => 'Ultimo pedido: '.$lastOrder.' / Ultima cita: '.$lastAppointment,
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
