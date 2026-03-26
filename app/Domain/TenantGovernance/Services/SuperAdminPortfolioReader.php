<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\OperationalPlaybookTask;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class SuperAdminPortfolioReader
{
    /**
     * @return array{
     *   portfolioTenants:list<Tenant>,
     *   monthlyProConversions:list<SubscriptionPayment>,
     *   activePlaybookTasks:list<OperationalPlaybookTask>,
     *   activePlaybookCount:int,
     *   overduePlaybookCount:int,
     *   unassignedPlaybookCount:int,
     *   commercialFollowupCount:int,
     *   renewalPlaybookCount:int,
     *   activationPlaybookCount:int,
     *   growthPlaybookCount:int,
     *   pendingPaymentsCount:int,
     *   usersCount:int
     * }
     */
    public function baseState(): array
    {
        $portfolioTenants = Tenant::query()
            ->with(['user:id,name,email', 'crmOwner:id,name,email', 'city:id,name,slug'])
            ->withCount([
                'products as products_total' => fn (Builder $query): Builder => $query->withoutGlobalScopes(),
                'services as services_total' => fn (Builder $query): Builder => $query->withoutGlobalScopes(),
            ])
            ->get();

        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $monthlyProConversions = SubscriptionPayment::query()
            ->withoutGlobalScopes()
            ->where('status', SubscriptionPayment::STATUS_APPROVED)
            ->whereNotNull('subscription_extended_at')
            ->whereBetween('subscription_extended_at', [$start, $end])
            ->with([
                'tenant' => fn (Builder $query): Builder => $query
                    ->withoutGlobalScopes()
                    ->with(['crmOwner:id,name,email', 'city:id,name,slug', 'user:id,name,email']),
            ])
            ->orderByDesc('subscription_extended_at')
            ->get();

        $tasks = OperationalPlaybookTask::query()
            ->withoutGlobalScopes()
            ->with(['tenant.user:id,name,email', 'tenant.crmOwner:id,name,email', 'assignee:id,name,email', 'adminOwner:id,name,email'])
            ->whereIn('status', [
                OperationalPlaybookTask::STATUS_OPEN,
                OperationalPlaybookTask::STATUS_IN_PROGRESS,
            ])
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('follow_up_status')
                    ->orWhere('follow_up_status', '!=', OperationalPlaybookTask::FOLLOW_UP_CLOSED);
            })
            ->orderByRaw("
                case priority
                    when 'critical' then 1
                    when 'high' then 2
                    else 3
                end
            ")
            ->orderByRaw("
                case
                    when due_at is null then 2
                    when due_at < now() then 0
                    else 1
                end
            ")
            ->orderBy('due_at')
            ->get();

        return [
            'portfolioTenants' => $portfolioTenants->all(),
            'monthlyProConversions' => $monthlyProConversions->all(),
            'activePlaybookTasks' => $tasks->all(),
            'activePlaybookCount' => $tasks->count(),
            'overduePlaybookCount' => $tasks->filter(fn (OperationalPlaybookTask $task): bool => $task->isOverdue())->count(),
            'unassignedPlaybookCount' => $tasks->filter(fn (OperationalPlaybookTask $task): bool => blank($task->admin_owner_id))->count(),
            'commercialFollowupCount' => $tasks->filter(fn (OperationalPlaybookTask $task): bool => in_array((string) $task->queue, ['leads', 'retention'], true))->count(),
            'renewalPlaybookCount' => $tasks->filter(fn (OperationalPlaybookTask $task): bool => (string) $task->queue === 'billing')->count(),
            'activationPlaybookCount' => $tasks->filter(fn (OperationalPlaybookTask $task): bool => (string) $task->queue === 'activation')->count(),
            'growthPlaybookCount' => $tasks->filter(fn (OperationalPlaybookTask $task): bool => (string) $task->queue === 'growth')->count(),
            'pendingPaymentsCount' => SubscriptionPayment::query()
                ->withoutGlobalScopes()
                ->where('status', SubscriptionPayment::STATUS_PENDING)
                ->count(),
            'usersCount' => User::query()->count(),
        ];
    }

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @param  Collection<int, SubscriptionPayment>  $monthlyProConversions
     * @return array<string,int>
     */
    public function metrics(Collection $portfolio, Collection $monthlyProConversions): array
    {
        $crmWatchlistCount = $portfolio
            ->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)
            ->count();

        return [
            'tenantsCount' => $portfolio->count(),
            'proTenantsCount' => $portfolio->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count(),
            'proConversionsThisMonthCount' => $monthlyProConversions
                ->map(fn (SubscriptionPayment $payment): string => (string) $payment->tenant_id)
                ->filter()
                ->unique()
                ->count(),
            'pendingTenantsCount' => $portfolio->filter(fn (Tenant $tenant): bool => (string) $tenant->getRawOriginal('status') === 'pending')->count(),
            'suspendedTenantsCount' => $portfolio->filter(fn (Tenant $tenant): bool => ! (bool) $tenant->getAttribute('is_active'))->count(),
            'catalogGapCount' => $portfolio->filter(fn (Tenant $tenant): bool => (int) ($tenant->products_total ?? 0) === 0 && (int) ($tenant->services_total ?? 0) === 0)->count(),
            'expiringProCount' => $portfolio->filter(fn (Tenant $tenant): bool => $tenant->is_pro && $tenant->subscription_ends_at instanceof Carbon && $tenant->subscription_ends_at->between(now(), now()->addDays(7)))->count(),
            'crmWatchlistCount' => $crmWatchlistCount,
            'atRiskTenantsCount' => $crmWatchlistCount,
            'crmOverdueAccountsCount' => $portfolio->filter(fn (Tenant $tenant): bool => $tenant->crm_next_follow_up_at instanceof Carbon && $tenant->crm_next_follow_up_at->isPast())->count(),
            'crmFollowupsTodayCount' => $portfolio->filter(fn (Tenant $tenant): bool => $tenant->crm_next_follow_up_at instanceof Carbon && $tenant->crm_next_follow_up_at->between(now()->startOfDay(), now()->endOfDay()))->count(),
            'crmOwnedAccountsCount' => $portfolio->filter(fn (Tenant $tenant): bool => filled($tenant->crm_owner_id))->count(),
        ];
    }

    /**
     * @return list<array{business:string,reference:string,amount:string,status:string,tone:string,reported_at:string}>
     */
    public function recentPayments(int $limit = 5): array
    {
        return SubscriptionPayment::query()
            ->withoutGlobalScopes()
            ->with('tenant')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function (SubscriptionPayment $payment): array {
                $status = match ((string) $payment->status) {
                    SubscriptionPayment::STATUS_PENDING => 'Pendiente',
                    SubscriptionPayment::STATUS_APPROVED => 'Aprobado',
                    SubscriptionPayment::STATUS_REJECTED => 'Rechazado',
                    default => 'Desconocido',
                };

                $tone = match ((string) $payment->status) {
                    SubscriptionPayment::STATUS_PENDING => 'warning',
                    SubscriptionPayment::STATUS_APPROVED => 'success',
                    SubscriptionPayment::STATUS_REJECTED => 'danger',
                    default => 'gray',
                };

                return [
                    'business' => (string) ($payment->tenant?->name ?? 'Negocio sin registro'),
                    'reference' => (string) ($payment->reference_number ?? 'Sin referencia'),
                    'amount' => 'L. '.number_format((float) $payment->amount, 2),
                    'status' => $status,
                    'tone' => $tone,
                    'reported_at' => optional($payment->created_at)?->format('d/m/Y H:i') ?? 'Sin fecha',
                ];
            })
            ->all();
    }
}
