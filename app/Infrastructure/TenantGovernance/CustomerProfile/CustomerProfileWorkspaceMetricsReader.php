<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Models\CustomerProfile;

final class CustomerProfileWorkspaceMetricsReader
{
    /**
     * @return array{openTasks:int,breachedTasks:int,openReplies:int,lastOrder:string,lastAppointment:string}
     */
    public function summarize(CustomerProfile $profile): array
    {
        return [
            'openTasks' => $profile->tasks()
                ->withoutGlobalScopes()
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count(),
            'breachedTasks' => $profile->tasks()
                ->withoutGlobalScopes()
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereNotNull('sla_due_at')
                ->where('sla_due_at', '<', now())
                ->count(),
            'openReplies' => $profile->notes()
                ->withoutGlobalScopes()
                ->where('requires_follow_up', true)
                ->whereIn('status', ['open', 'in_progress', 'pending_customer'])
                ->count(),
            'lastOrder' => $profile->last_order_at?->diffForHumans() ?? 'Sin pedidos',
            'lastAppointment' => $profile->last_appointment_at?->diffForHumans() ?? 'Sin citas',
        ];
    }
}
