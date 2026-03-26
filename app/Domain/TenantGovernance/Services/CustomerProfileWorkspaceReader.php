<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;

final class CustomerProfileWorkspaceReader
{
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
        $query = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $current->tenant_id)
            ->where('id', '!=', $current->getKey())
            ->orderByDesc('last_seen_at')
            ->orderByDesc('updated_at')
            ->limit(20);

        if (trim($search) !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        return $query
            ->get()
            ->mapWithKeys(fn (CustomerProfile $profile): array => [(int) $profile->getKey() => $this->formatDuplicateLabel($profile)])
            ->all();
    }

    public function duplicateLabel(CustomerProfile $current, mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $profile = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $current->tenant_id)
            ->find((int) $value);

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
