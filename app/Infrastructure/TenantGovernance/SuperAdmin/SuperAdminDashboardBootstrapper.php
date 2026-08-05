<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\SuperAdmin;

use App\Filament\Pages\Dashboard;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SuperAdminDashboardBootstrapper
{
    public function boot(Dashboard $page): void
    {
        $page->tenantsCount = (int) Tenant::query()->count();
        $page->proTenantsCount = (int) Tenant::query()
            ->whereIn('plan_type', ['pro', 'enterprise'])
            ->whereIn('subscription_status', ['active', 'trial'])
            ->count();
        $page->usersCount = (int) User::query()->count();
        $page->proConversionsThisMonthCount = (int) Tenant::query()
            ->whereIn('plan_type', ['pro', 'enterprise'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $page->pendingPaymentsCount = (int) Tenant::query()
            ->whereIn('subscription_status', ['pending', 'past_due'])
            ->count();
        $page->pendingTenantsCount = (int) Tenant::query()
            ->where(function ($q): void {
                $q->whereNull('is_active')->orWhere('is_active', false);
            })
            ->count();
        $page->suspendedTenantsCount = (int) Tenant::query()
            ->where('subscription_status', 'suspended')
            ->count();
        $page->atRiskTenantsCount = (int) Tenant::query()
            ->whereIn('subscription_status', ['past_due', 'incomplete'])
            ->count();
        $page->expiringProCount = 0;
        $page->catalogGapCount = 0;
        $page->openAttentionCount = $page->pendingPaymentsCount + $page->pendingTenantsCount + $page->atRiskTenantsCount;

        $page->activePlaybookCount = 0;
        $page->overduePlaybookCount = 0;
        $page->unassignedPlaybookCount = 0;
        $page->crmOverdueAccountsCount = 0;
        $page->crmWatchlistCount = 0;
        $page->crmOwnedAccountsCount = 0;
        $page->crmFollowupsTodayCount = 0;
        $page->commercialFollowupCount = 0;
        $page->renewalPlaybookCount = 0;
        $page->activationPlaybookCount = 0;
        $page->growthPlaybookCount = 0;

        $page->tenantsUrl = $this->safeRoute('filament.admin.resources.tenants.index');
        $page->usersUrl = $this->safeRoute('filament.admin.resources.users.index');
        $page->paymentsUrl = $this->safeRoute('filament.admin.resources.payments.index');
        $page->settingsUrl = $this->safeRoute('filament.admin.pages.settings');
        $page->billingUrl = $this->safeRoute('filament.admin.pages.billing');
        $page->operationsUrl = $this->safeRoute('filament.admin.pages.dashboard');

        $page->attentionQueues = [];
        $page->operationalLanes = [];
        $page->globalWorkboard = [];
        $page->assigneeLoad = [];
        $page->ownerPortfolio = [];
        $page->teamPortfolio = [];
        $page->slaBacklog = [];
        $page->commercialFunnel = [];
        $page->ownerGoals = [];
        $page->teamGoals = [];
        $page->verticalMonthlyGoals = [];
        $page->planCohorts = [];
        $page->cityCohorts = [];
        $page->channelCohorts = [];
        $page->ownerCohorts = [];
        $page->verticalChurn = [];
        $page->revenuePlaybooks = [];
        $page->verticalJourneyUplift = [];
        $page->networkJourneyPaths = [];
        $page->churnWatchlist = [];
        $page->commercialFollowups = [];
        $page->recentPayments = [];

        $modeCohorts = $this->modeCohorts();
        $page->activationCohorts = $modeCohorts;
        $page->segmentExpansion = $this->segmentExpansionFromModes($modeCohorts);
    }

    private function modeCohorts(): array
    {
        $base = Tenant::query()
            ->selectRaw("COALESCE(NULLIF(commercial_mode, ''), 'products') as mode")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
            ->selectRaw("SUM(CASE WHEN plan_type IN ('pro','enterprise') AND subscription_status IN ('active','trial') THEN 1 ELSE 0 END) as pro")
            ->groupBy('mode')
            ->get();

        $onboardingByMode = [];
        if (Schema::hasTable('tenant_onboarding_states')) {
            $onboardingByMode = DB::table('tenant_onboarding_states as tos')
                ->join('tenants as t', 't.id', '=', 'tos.tenant_id')
                ->selectRaw("COALESCE(NULLIF(t.commercial_mode, ''), 'products') as mode")
                ->selectRaw('AVG(tos.progress) as avg_progress')
                ->groupBy('mode')
                ->pluck('avg_progress', 'mode')
                ->map(fn ($value) => (int) round((float) $value))
                ->toArray();
        }

        return $base->map(function ($row) use ($onboardingByMode): array {
            $mode = (string) $row->mode;
            $total = (int) $row->total;
            $active = (int) $row->active;
            $pro = (int) $row->pro;
            $activationRate = $total > 0 ? round(($active / $total) * 100, 1).'%' : '0%';

            return [
                'label' => $this->modeLabel($mode),
                'count' => $total,
                'activation_rate' => $activationRate,
                'onboarding' => (int) ($onboardingByMode[$mode] ?? 0),
                'active' => $active,
                'pro' => $pro,
                'href' => $this->safeRoute('filament.admin.resources.tenants.index'),
            ];
        })->values()->all();
    }

    private function segmentExpansionFromModes(array $modeCohorts): array
    {
        return collect($modeCohorts)
            ->map(function (array $cohort): array {
                $count = (int) ($cohort['count'] ?? 0);
                $active = (int) ($cohort['active'] ?? 0);
                $pro = (int) ($cohort['pro'] ?? 0);
                $expansion = max(0, $active - $pro);
                $coverage = $count > 0 ? round(($active / $count) * 100, 1).'%' : '0%';

                return [
                    'segment' => (string) ($cohort['label'] ?? 'Modo'),
                    'count' => $count,
                    'expansion' => $expansion,
                    'activation' => $active,
                    'pro' => $pro,
                    'converted' => $pro,
                    'watch' => max(0, $count - $active),
                    'coverage' => $coverage,
                    'tone' => $active >= max(1, (int) floor($count * 0.6)) ? 'success' : 'warning',
                    'href' => (string) ($cohort['href'] ?? '#'),
                ];
            })
            ->values()
            ->all();
    }

    private function modeLabel(string $mode): string
    {
        return match ($mode) {
            'appointments' => 'Starter Appointments',
            'consulting_leads' => 'Starter Consulting',
            'hybrid' => 'Hybrid',
            default => 'Starter Products',
        };
    }

    private function safeRoute(string $name): string
    {
        try {
            return route($name);
        } catch (\Throwable) {
            return '#';
        }
    }
}
