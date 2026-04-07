<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;

class CustomerConversationRouting
{
    /**
     * @return array{
     *     assignment_group:string,
     *     assignment_group_label:string,
     *     response_sla_minutes:int,
     *     escalation_minutes:int
     * }
     */
    public function resolve(CustomerProfile $profile, string $channel = 'crm'): array
    {
        $segmentKey = (string) ($profile->manual_segment ?: $profile->customer_segment ?: 'new');
        $tenant = $profile->tenant;
        $businessSegmentKey = $tenant instanceof \App\Models\Tenant && filled($tenant->business_segment_key)
            ? (string) $tenant->business_segment_key
            : null;
        $groups = (array) config('customer-operations.groups', []);
        $segmentGroups = (array) config('customer-operations.segment_groups', []);
        $baseGroup = (string) ($segmentGroups[$segmentKey] ?? 'sales');
        $override = $businessSegmentKey !== null
            ? (array) config("customer-operations.business_segment_overrides.{$businessSegmentKey}", [])
            : [];
        $groupKey = (string) ($override['group'] ?? $baseGroup);
        $groupDefinition = (array) ($groups[$groupKey] ?? $groups['sales'] ?? []);
        $channelDelta = (int) data_get(config('customer-operations.channels', []), "{$channel}.sla_delta_minutes", 0);
        $responseSlaMinutes = max(15, (int) ($override['sla_minutes'] ?? $groupDefinition['sla_minutes'] ?? 90) + $channelDelta);
        $escalationMinutes = max($responseSlaMinutes, (int) ($override['escalation_minutes'] ?? $groupDefinition['escalation_minutes'] ?? ($responseSlaMinutes + 30)));

        return [
            'assignment_group' => $groupKey,
            'assignment_group_label' => (string) ($groupDefinition['label'] ?? ucfirst($groupKey)),
            'response_sla_minutes' => $responseSlaMinutes,
            'escalation_minutes' => $escalationMinutes,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return collect((array) config('customer-operations.groups', []))
            ->mapWithKeys(static fn (array $definition, string $key): array => [
                $key => (string) ($definition['label'] ?? ucfirst($key)),
            ])
            ->all();
    }
}
