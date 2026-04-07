<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Readers;

use App\Models\CustomerProfile;
use App\Models\CustomerTask;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

final class TenantOperationalComparisonReader
{
    /**
     * @return array{
     *   ownerComparisons:list<array{name:string,revenue:string,customers:int,openTasks:int,overdueTasks:int,repeatRate:string,context:string}>,
     *   locationComparisons:list<array{name:string,revenue:string,customers:int,openTasks:int,overdueTasks:int,repeatRate:string,context:string}>
     * }
     */
    public function summarize(Tenant $tenant, callable $formatCurrency): array
    {
        $tenantId = (string) $tenant->getKey();

        $profiles = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get([
                'id',
                'assigned_user_id',
                'location_id',
                'customer_segment',
                'total_spent',
                'repeat_purchase_count',
            ]);
        $tasks = CustomerTask::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->get([
                'assigned_user_id',
                'location_id',
                'status',
                'sla_due_at',
            ]);

        $userLabels = User::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $profiles->pluck('assigned_user_id')->merge($tasks->pluck('assigned_user_id'))->filter()->unique()->all())
            ->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(string) $id => (string) $name])
            ->all();
        $locationLabels = Location::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $profiles->pluck('location_id')->merge($tasks->pluck('location_id'))->filter()->unique()->all())
            ->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(string) $id => (string) $name])
            ->all();

        return [
            'ownerComparisons' => $this->buildRows($profiles, $tasks, 'assigned_user_id', $userLabels, 'Sin owner', $formatCurrency),
            'locationComparisons' => $this->buildRows($profiles, $tasks, 'location_id', $locationLabels, 'Sin sede', $formatCurrency),
        ];
    }

    /**
     * @param  Collection<int, CustomerProfile>  $profiles
     * @param  Collection<int, CustomerTask>  $tasks
     * @param  array<string, string>  $labels
     * @return list<array{name: string, revenue: string, customers: int, openTasks: int, overdueTasks: int, repeatRate: string, context: string}>
     */
    private function buildRows(
        Collection $profiles,
        Collection $tasks,
        string $dimensionKey,
        array $labels,
        string $emptyLabel,
        callable $formatCurrency,
    ): array {
        return $profiles->pluck($dimensionKey)
            ->merge($tasks->pluck($dimensionKey))
            ->map(fn (mixed $value): string => (string) ($value ?: 'none'))
            ->unique()
            ->values()
            ->map(function (string $key) use ($profiles, $tasks, $dimensionKey, $labels, $emptyLabel, $formatCurrency): array {
                $profileSlice = $profiles->filter(fn (CustomerProfile $profile): bool => (string) ($profile->{$dimensionKey} ?: 'none') === $key);
                $taskSlice = $tasks->filter(fn (CustomerTask $task): bool => (string) ($task->{$dimensionKey} ?: 'none') === $key);
                $customerCount = $profileSlice->count();
                $repeatCustomers = $profileSlice->filter(fn (CustomerProfile $profile): bool => (int) $profile->repeat_purchase_count > 0)->count();
                $overdueTasks = $taskSlice->filter(fn (CustomerTask $task): bool => $task->sla_due_at !== null && $task->sla_due_at->isPast())->count();
                $topSegment = (string) ($profileSlice->pluck('customer_segment')->filter()->countBy()->sortDesc()->keys()->first() ?? 'sin segmento');
                $revenue = (float) $profileSlice->sum('total_spent');

                return [
                    'name' => $labels[$key] ?? $emptyLabel,
                    'revenue' => $formatCurrency($revenue),
                    'customers' => $customerCount,
                    'openTasks' => $taskSlice->count(),
                    'overdueTasks' => $overdueTasks,
                    'repeatRate' => $customerCount > 0 ? number_format(($repeatCustomers / $customerCount) * 100, 1).'%' : '0.0%',
                    'context' => 'Segmento dominante: '.str($topSegment)->replace('_', ' ')->title()->value(),
                ];
            })
            ->sortByDesc(fn (array $row): float => ($row['overdueTasks'] * 1000) + ($row['openTasks'] * 100) + (float) str_replace(['L. ', ','], '', $row['revenue']))
            ->take(4)
            ->values()
            ->all();
    }
}
