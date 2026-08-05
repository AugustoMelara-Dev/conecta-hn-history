<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

// @withoutGlobalScopes-safety INFRA_TENANT_FILTER: every projection query carries an explicit tenant predicate.

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Markets\TenantMarketResolver;
use App\Application\Operations\OperationalCockpit;
use App\Application\Operations\OperationalCockpitSnapshot;
use App\Application\Operations\OperationalKnowledgeHealth;
use App\Application\Operations\OperationalKnowledgeIssue;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final readonly class EloquentOperationalCockpit implements OperationalCockpit
{
    public function __construct(
        private TenantPermissionGuardInterface $permissionGuard,
        private TenantMarketResolver $marketResolver,
        private OperationalKnowledgeHealth $health,
    ) {}

    public function snapshot(string $tenantId, string $actorId): OperationalCockpitSnapshot
    {
        $tenant = Tenant::query()->withoutGlobalScopes()->findOrFail($tenantId);
        $actor = User::query()->withoutGlobalScopes()->findOrFail($actorId);
        if (! $this->permissionGuard->allows(
            $actor,
            $tenant,
            Capability::ANALYTICS_BASIC,
            Permission::ANALYTICS_VIEW,
        )) {
            throw new AuthorizationException('Actor cannot view this operational cockpit.');
        }

        $now = CarbonImmutable::now();
        $currency = $this->marketResolver->resolve($tenant)->currencyCode;
        $degraded = [];
        foreach ([
            'verified_commercial_outcomes',
            'interactions',
            'crm_deals',
            'orders',
            'appointments',
            'quotes',
            'catalog_import_batches',
            'ai_executions',
            'ai_usage_logs',
        ] as $source) {
            if (! Schema::hasTable($source)) {
                $degraded[] = $source;
            }
        }

        $outcomeMinor = (int) $this->sumWhenAvailable(
            'verified_commercial_outcomes',
            $degraded,
            fn () => DB::table('verified_commercial_outcomes')
                ->where('tenant_id', $tenantId)
                ->where('currency', $currency)
                ->whereDate('source_occurred_at', $now)
                ->sum('amount_minor'),
        );
        $activeOpportunities = $this->countWhenAvailable(
            'crm_deals',
            $degraded,
            fn () => DB::table('crm_deals')
                ->where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->count(),
        );
        $attentionInteractions = $this->countWhenAvailable(
            'interactions',
            $degraded,
            fn () => DB::table('interactions')
                ->where('tenant_id', $tenantId)
                ->whereIn('type', ['whatsapp_click', 'call'])
                ->where('created_at', '>=', $now->subDays(7))
                ->count(),
        );
        $agingOpportunities = $this->countWhenAvailable(
            'crm_deals',
            $degraded,
            fn () => DB::table('crm_deals')
                ->where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->where('created_at', '<=', $now->subDays(14))
                ->count(),
        );
        $upcomingOrders = $this->countWhenAvailable(
            'orders',
            $degraded,
            fn () => DB::table('orders')
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['pending', 'confirmed', 'processing', 'ready'])
                ->whereNull('deleted_at')
                ->count(),
        );
        $upcomingReservations = $this->countWhenAvailable(
            'appointments',
            $degraded,
            fn () => DB::table('appointments')
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['pending', 'confirmed'])
                ->whereBetween('scheduled_at', [$now, $now->addDays(7)])
                ->count(),
        );
        $overdueQuotes = $this->countWhenAvailable(
            'quotes',
            $degraded,
            fn () => DB::table('quotes')
                ->where('tenant_id', $tenantId)
                ->where('status', 'issued')
                ->where('expires_at', '<', $now)
                ->count(),
        );
        $failedImports = $this->countWhenAvailable(
            'catalog_import_batches',
            $degraded,
            fn () => DB::table('catalog_import_batches')
                ->where('tenant_id', $tenantId)
                ->where('status', 'failed')
                ->count(),
        );
        $failedAutomations = $this->countWhenAvailable(
            'ai_executions',
            $degraded,
            fn () => DB::table('ai_executions')
                ->where('billing_tenant_id', $tenantId)
                ->where('status', 'failed')
                ->count(),
        );
        $aiCost = (float) $this->sumWhenAvailable(
            'ai_usage_logs',
            $degraded,
            fn () => DB::table('ai_usage_logs')
                ->where('tenant_id', $tenantId)
                ->whereDate('created_at', $now)
                ->sum('cost'),
        );
        $healthIssues = $this->health->forTenant($tenantId);
        [$nextAction, $nextLabel, $nextUrl] = $this->nextAction(
            $tenant,
            $failedImports,
            $overdueQuotes,
            $healthIssues,
            $agingOpportunities,
        );
        $orderEvidence = in_array('orders', $degraded, true)
            ? []
            : DB::table('orders')
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['pending', 'confirmed', 'processing', 'ready'])
                ->limit(20)
                ->pluck('order_number')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();
        $evidenceIds = array_values(array_filter([
            ...$orderEvidence,
            ...array_map(
                static fn ($issue): string => $issue->evidenceId,
                $healthIssues,
            ),
        ]));

        return new OperationalCockpitSnapshot(
            tenantId: $tenantId,
            currency: $currency,
            verifiedOutcomeMinor: $outcomeMinor,
            attentionRequired: $attentionInteractions + $overdueQuotes + $failedImports + $failedAutomations,
            activeOpportunities: $activeOpportunities,
            agingOpportunities: $agingOpportunities,
            upcomingOrders: $upcomingOrders,
            upcomingReservations: $upcomingReservations,
            overdueQuotes: $overdueQuotes,
            failedImports: $failedImports,
            failedAutomations: $failedAutomations,
            aiAllowance: max(
                0,
                (float) $tenant->ai_balance - (float) $tenant->ai_reserved_balance,
            ),
            aiCostToday: $aiCost,
            healthIssues: $healthIssues,
            nextAction: $nextAction,
            nextActionLabel: $nextLabel,
            nextActionUrl: $nextUrl,
            evidenceIds: $evidenceIds,
            degradedSources: $degraded,
            generatedAt: $now,
        );
    }

    /** @param list<string> $degraded */
    private function countWhenAvailable(string $table, array $degraded, callable $query): int
    {
        return in_array($table, $degraded, true) ? 0 : (int) $query();
    }

    /** @param list<string> $degraded */
    private function sumWhenAvailable(string $table, array $degraded, callable $query): int|float
    {
        return in_array($table, $degraded, true) ? 0 : (float) $query();
    }

    /**
     * @param  list<OperationalKnowledgeIssue>  $issues
     * @return array{string, string, string}
     */
    private function nextAction(
        Tenant $tenant,
        int $failedImports,
        int $overdueQuotes,
        array $issues,
        int $agingOpportunities,
    ): array {
        if ($failedImports > 0) {
            return [
                'resolve_failed_imports',
                'Resolve failed catalog imports',
                "/app/{$tenant->slug}/products",
            ];
        }
        if ($overdueQuotes > 0) {
            return ['follow_up_overdue_quotes', 'Follow up overdue quotes', "/app/{$tenant->slug}"];
        }
        if ($issues !== []) {
            return [
                'repair_knowledge_health',
                'Repair buyer-facing business knowledge',
                $issues[0]->remediationUrl,
            ];
        }
        if ($agingOpportunities > 0) {
            return [
                'advance_aging_opportunities',
                'Advance aging opportunities',
                "/app/{$tenant->slug}/crm-deals",
            ];
        }

        return ['monitor_operations', 'Monitor today’s operations', "/app/{$tenant->slug}"];
    }
}
