<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SuperAdminRevenueInsightsReader
{
    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @param  Collection<int, SubscriptionPayment>  $monthlyProConversions
     * @return list<array{vertical:string,target:int,converted:int,pipeline:int,pro:int,attainment:string,focus:string,tone:string,href:string}>
     */
    public function verticalMonthlyGoals(Collection $portfolio, Collection $monthlyProConversions, string $billingUrl): array
    {
        /** @var array<string, array{label?: string, target?: int, focus?: string}> $targets */
        $targets = config('revenue-ops.monthly_vertical_goals', []);

        $configuredSegments = collect(array_keys($targets))
            ->reject(fn (string $segmentKey): bool => $segmentKey === 'default');

        $activeSegments = $portfolio
            ->map(fn (Tenant $tenant): string => $this->segmentKeyForTenant($tenant))
            ->merge($monthlyProConversions->map(fn (SubscriptionPayment $payment): string => $this->segmentKeyForPayment($payment)))
            ->filter()
            ->unique();

        return $configuredSegments
            ->merge($activeSegments)
            ->unique()
            ->map(function (string $segmentKey) use ($portfolio, $monthlyProConversions, $targets, $billingUrl): array {
                $verticalTenants = $portfolio
                    ->filter(fn (Tenant $tenant): bool => $this->segmentKeyForTenant($tenant) === $segmentKey);
                $count = $verticalTenants->count();
                $converted = $this->convertedTenantCount(
                    $monthlyProConversions,
                    fn (SubscriptionPayment $payment): bool => $this->segmentKeyForPayment($payment) === $segmentKey
                );
                $pipeline = $verticalTenants->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count();
                $pro = $verticalTenants->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count();

                /** @var array{label?: string, target?: int, focus?: string} $defaultTarget */
                $defaultTarget = $targets['default'] ?? [];
                /** @var array{label?: string, target?: int, focus?: string} $targetConfig */
                $targetConfig = $targets[$segmentKey] ?? [];
                $target = max(1, (int) ($targetConfig['target'] ?? $defaultTarget['target'] ?? max(1, $pipeline)));
                $attainment = number_format(($converted / max(1, $target)) * 100, 1).'%';

                return [
                    'vertical' => (string) ($targetConfig['label'] ?? $this->segmentLabel($segmentKey)),
                    'target' => $target,
                    'converted' => $converted,
                    'pipeline' => $pipeline,
                    'pro' => $pro,
                    'attainment' => $attainment,
                    'focus' => (string) ($targetConfig['focus'] ?? $defaultTarget['focus'] ?? 'Sin foco comercial configurado para este vertical.'),
                    'tone' => $converted >= $target ? 'success' : (($converted + $pipeline) >= $target ? 'warning' : 'info'),
                    'href' => $billingUrl,
                ];
            })
            ->filter(fn (array $item): bool => $item['target'] > 0 || $item['converted'] > 0 || $item['pipeline'] > 0 || $item['pro'] > 0)
            ->sortByDesc(fn (array $item): int => ($item['converted'] * 100) + ($item['pipeline'] * 50) + ($item['target'] * 10) + $item['pro'])
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @return list<array{label:string,count:int,activation_rate:string,onboarding:int,active:int,pro:int,href:string}>
     */
    public function activationCohorts(Collection $portfolio, string $tenantsUrl): array
    {
        return collect(range(5, 0))
            ->map(fn (int $monthsAgo): Carbon => now()->startOfMonth()->subMonths($monthsAgo))
            ->map(function (Carbon $month) use ($portfolio, $tenantsUrl): array {
                $cohortTenants = $portfolio->filter(function (Tenant $tenant) use ($month): bool {
                    return $tenant->created_at instanceof Carbon
                        && $tenant->created_at->isSameMonth($month);
                });

                $count = $cohortTenants->count();
                $onboarding = $cohortTenants->filter(function (Tenant $tenant): bool {
                    return in_array((string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW), [
                        Tenant::CRM_STATUS_NEW,
                        Tenant::CRM_STATUS_ONBOARDING,
                    ], true);
                })->count();
                $active = $cohortTenants->filter(fn (Tenant $tenant): bool => $this->isActivatedTenant($tenant))->count();
                $pro = $cohortTenants->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count();

                return [
                    'label' => $month->translatedFormat('M Y'),
                    'count' => $count,
                    'activation_rate' => $count > 0 ? number_format(($active / $count) * 100, 1).'%' : '0.0%',
                    'onboarding' => $onboarding,
                    'active' => $active,
                    'pro' => $pro,
                    'href' => $tenantsUrl,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @param  Collection<int, SubscriptionPayment>  $monthlyProConversions
     * @return list<array{plan:string,count:int,converted:int,expansion:int,watch:int,pro:int,tone:string,href:string}>
     */
    public function planCohorts(Collection $portfolio, Collection $monthlyProConversions, string $billingUrl): array
    {
        $planKeys = $portfolio
            ->map(fn (Tenant $tenant): string => $this->planKeyForTenant($tenant))
            ->merge($monthlyProConversions->map(fn (SubscriptionPayment $payment): string => $this->planKeyForPayment($payment)))
            ->filter()
            ->unique();

        return $planKeys
            ->map(function (string $planKey) use ($portfolio, $monthlyProConversions, $billingUrl): array {
                $tenants = $portfolio
                    ->filter(fn (Tenant $tenant): bool => $this->planKeyForTenant($tenant) === $planKey);
                $count = $tenants->count();
                $converted = $this->convertedTenantCount(
                    $monthlyProConversions,
                    fn (SubscriptionPayment $payment): bool => $this->planKeyForPayment($payment) === $planKey
                );
                $expansion = $tenants->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count();
                $watch = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $pro = $tenants->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count();

                return [
                    'plan' => $this->planLabel($planKey),
                    'count' => $count,
                    'converted' => $converted,
                    'expansion' => $expansion,
                    'watch' => $watch,
                    'pro' => $pro,
                    'tone' => $converted > 0 ? 'success' : ($watch > 0 ? 'warning' : 'info'),
                    'href' => $billingUrl,
                ];
            })
            ->filter(fn (array $item): bool => $item['count'] > 0 || $item['converted'] > 0 || $item['pro'] > 0)
            ->sortByDesc(fn (array $item): int => ($item['converted'] * 100) + ($item['expansion'] * 50) + ($item['count'] * 10))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @param  Collection<int, SubscriptionPayment>  $monthlyProConversions
     * @return list<array{city:string,count:int,activation_rate:string,expansion:int,watch:int,pro:int,converted:int,tone:string,href:string}>
     */
    public function cityCohorts(Collection $portfolio, Collection $monthlyProConversions, string $tenantsUrl): array
    {
        return $portfolio
            ->groupBy(fn (Tenant $tenant): string => (string) ($tenant->city?->name ?: 'Sin ciudad'))
            ->map(function (Collection $tenants, string $city) use ($monthlyProConversions, $tenantsUrl): array {
                $count = $tenants->count();
                $activated = $tenants->filter(fn (Tenant $tenant): bool => $this->isActivatedTenant($tenant))->count();
                $expansion = $tenants->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count();
                $watch = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $pro = $tenants->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count();
                $converted = $this->convertedTenantCount(
                    $monthlyProConversions,
                    fn (SubscriptionPayment $payment): bool => $this->cityLabelForPayment($payment) === $city
                );

                return [
                    'city' => $city,
                    'count' => $count,
                    'activation_rate' => $count > 0 ? number_format(($activated / $count) * 100, 1).'%' : '0.0%',
                    'expansion' => $expansion,
                    'watch' => $watch,
                    'pro' => $pro,
                    'converted' => $converted,
                    'tone' => $converted > 0 ? 'success' : ($watch > 0 ? 'warning' : ($expansion > 0 ? 'info' : 'gray')),
                    'href' => $tenantsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['converted'] * 100) + ($item['count'] * 50) + ($item['watch'] * 25) + ($item['expansion'] * 10))
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @return list<array{channel:string,count:int,activation_rate:string,expansion:int,watch:int,pro:int,copy:string,tone:string,href:string}>
     */
    public function channelCohorts(Collection $portfolio, string $tenantsUrl): array
    {
        return $portfolio
            ->groupBy(fn (Tenant $tenant): string => $this->closingChannelKey($tenant))
            ->map(function (Collection $tenants, string $channelKey) use ($tenantsUrl): array {
                $count = $tenants->count();
                $activated = $tenants->filter(fn (Tenant $tenant): bool => $this->isActivatedTenant($tenant))->count();
                $expansion = $tenants->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count();
                $watch = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $pro = $tenants->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count();

                return [
                    'channel' => $this->closingChannelLabel($channelKey),
                    'count' => $count,
                    'activation_rate' => $count > 0 ? number_format(($activated / $count) * 100, 1).'%' : '0.0%',
                    'expansion' => $expansion,
                    'watch' => $watch,
                    'pro' => $pro,
                    'copy' => $this->closingChannelCopy($channelKey),
                    'tone' => $channelKey === 'none' ? 'warning' : ($expansion > 0 ? 'success' : 'info'),
                    'href' => $tenantsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['count'] * 100) + ($item['expansion'] * 50) + ($item['watch'] * 25))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @param  Collection<int, SubscriptionPayment>  $monthlyProConversions
     * @return list<array{segment:string,count:int,expansion:int,activation:int,pro:int,converted:int,watch:int,coverage:string,tone:string,href:string}>
     */
    public function segmentExpansion(Collection $portfolio, Collection $monthlyProConversions, string $tenantsUrl): array
    {
        return $portfolio
            ->groupBy(fn (Tenant $tenant): string => $this->segmentKeyForTenant($tenant))
            ->map(function (Collection $tenants, string $segmentKey) use ($monthlyProConversions, $tenantsUrl): array {
                $count = $tenants->count();
                $expansion = $tenants->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count();
                $activation = $tenants->filter(fn (Tenant $tenant): bool => $this->needsActivationPush($tenant))->count();
                $pro = $tenants->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count();
                $converted = $this->convertedTenantCount(
                    $monthlyProConversions,
                    fn (SubscriptionPayment $payment): bool => $this->segmentKeyForPayment($payment) === $segmentKey
                );
                $watch = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $ownerCoverage = $tenants->filter(fn (Tenant $tenant): bool => filled($tenant->crm_owner_id))->count();

                return [
                    'segment' => $this->segmentLabel($segmentKey),
                    'count' => $count,
                    'expansion' => $expansion,
                    'activation' => $activation,
                    'pro' => $pro,
                    'converted' => $converted,
                    'watch' => $watch,
                    'coverage' => $count > 0 ? number_format(($ownerCoverage / $count) * 100, 1).'%' : '0.0%',
                    'tone' => $converted > 0 ? 'success' : ($expansion > 0 ? 'warning' : ($watch > 0 ? 'danger' : 'info')),
                    'href' => $tenantsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['converted'] * 120) + ($item['expansion'] * 100) + ($item['watch'] * 50) + $item['count'])
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @param  Collection<int, SubscriptionPayment>  $monthlyProConversions
     * @return list<array{owner:string,count:int,converted:int,expansion:int,watch:int,pro:int,tone:string,href:string}>
     */
    public function ownerCohorts(Collection $portfolio, Collection $monthlyProConversions, string $operationsUrl): array
    {
        return $portfolio
            ->groupBy(fn (Tenant $tenant): string => (string) ($tenant->crm_owner_id ?: 'unassigned'))
            ->map(function (Collection $tenants, string $ownerKey) use ($monthlyProConversions, $operationsUrl): array {
                /** @var Tenant|null $firstTenant */
                $firstTenant = $tenants->first();
                $converted = $this->convertedTenantCount(
                    $monthlyProConversions,
                    fn (SubscriptionPayment $payment): bool => $this->ownerKeyForPayment($payment) === $ownerKey
                );
                $expansion = $tenants->filter(fn (Tenant $tenant): bool => $this->isExpansionReady($tenant))->count();
                $watch = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $pro = $tenants->filter(fn (Tenant $tenant): bool => $tenant->is_pro)->count();

                return [
                    'owner' => $ownerKey === 'unassigned'
                        ? 'Sin owner interno'
                        : (string) (($firstTenant?->crmOwner?->name ?: $firstTenant?->crmOwner?->email) ?? 'Owner interno'),
                    'count' => $tenants->count(),
                    'converted' => $converted,
                    'expansion' => $expansion,
                    'watch' => $watch,
                    'pro' => $pro,
                    'tone' => $converted > 0 ? 'success' : ($watch > 0 ? 'warning' : ($ownerKey === 'unassigned' ? 'gray' : 'info')),
                    'href' => $operationsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['converted'] * 100) + ($item['watch'] * 50) + ($item['expansion'] * 25) + $item['count'])
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @return list<array{vertical:string,count:int,watch:int,critical:int,suspended:int,renewal:int,tone:string,href:string}>
     */
    public function verticalChurn(Collection $portfolio, string $tenantsUrl): array
    {
        return $portfolio
            ->groupBy(fn (Tenant $tenant): string => $this->segmentKeyForTenant($tenant))
            ->map(function (Collection $tenants, string $segmentKey) use ($tenantsUrl): array {
                $watch = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) > 0)->count();
                $critical = $tenants->filter(fn (Tenant $tenant): bool => (int) ($tenant->crm_watch_score ?? 0) >= 90)->count();
                $suspended = $tenants->filter(fn (Tenant $tenant): bool => ! (bool) $tenant->getAttribute('is_active'))->count();
                $renewal = $tenants->filter(fn (Tenant $tenant): bool => $this->isRenewalMotionCandidate($tenant))->count();

                return [
                    'vertical' => $this->segmentLabel($segmentKey),
                    'count' => $tenants->count(),
                    'watch' => $watch,
                    'critical' => $critical,
                    'suspended' => $suspended,
                    'renewal' => $renewal,
                    'tone' => $critical > 0 ? 'danger' : ($watch > 0 || $renewal > 0 ? 'warning' : 'info'),
                    'href' => $tenantsUrl,
                ];
            })
            ->sortByDesc(fn (array $item): int => ($item['critical'] * 100) + ($item['watch'] * 50) + $item['renewal'])
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SubscriptionPayment>  $monthlyProConversions
     */
    public function convertedTenantCount(Collection $monthlyProConversions, callable $filter): int
    {
        return $monthlyProConversions
            ->filter($filter)
            ->map(fn (SubscriptionPayment $payment): string => (string) $payment->tenant_id)
            ->filter()
            ->unique()
            ->count();
    }

    public function segmentKeyForPayment(SubscriptionPayment $payment): string
    {
        return $payment->tenant instanceof Tenant
            ? $this->segmentKeyForTenant($payment->tenant)
            : 'retail_general';
    }

    public function segmentKeyForTenant(Tenant $tenant): string
    {
        $segmentKey = (string) ($tenant->getRawOriginal('business_segment_key') ?? '');

        return $segmentKey !== '' ? $segmentKey : 'retail_general';
    }

    public function segmentLabel(string $segmentKey): string
    {
        $label = config("business-segments.profiles.{$segmentKey}.label");

        return is_string($label) && $label !== ''
            ? $label
            : Str::of($segmentKey === '' ? 'general' : $segmentKey)->replace('_', ' ')->title()->value();
    }

    public function planKeyForTenant(Tenant $tenant): string
    {
        $planKey = (string) ($tenant->getRawOriginal('plan_type') ?? '');

        if ($planKey !== '') {
            return $planKey;
        }

        return $tenant->is_pro ? 'pro' : Tenant::PLAN_SEMILLA;
    }

    public function planKeyForPayment(SubscriptionPayment $payment): string
    {
        $originPlan = (string) ($payment->plan_before_upgrade ?? '');

        if ($originPlan !== '') {
            return $originPlan;
        }

        return $payment->tenant instanceof Tenant
            ? $this->planKeyForTenant($payment->tenant)
            : 'pro';
    }

    public function planLabel(string $planKey): string
    {
        return match ($planKey) {
            'pro' => 'PRO',
            Tenant::PLAN_CRECIMIENTO => 'Crecimiento',
            Tenant::PLAN_COSECHA => 'Cosecha',
            default => 'Semilla',
        };
    }

    public function cityLabelForPayment(SubscriptionPayment $payment): string
    {
        return (string) ($payment->tenant?->city?->name ?: 'Sin ciudad');
    }

    public function ownerKeyForPayment(SubscriptionPayment $payment): string
    {
        return (string) ($payment->tenant?->crm_owner_id ?: 'unassigned');
    }

    public function closingChannelKey(Tenant $tenant): string
    {
        $signals = collect([
            filled($tenant->whatsapp_number) ? 'whatsapp' : null,
            filled($tenant->website_url) ? 'website' : null,
            filled($tenant->phone) ? 'phone' : null,
        ])->filter()->values();

        if ($signals->count() >= 2) {
            return 'multi';
        }

        return (string) ($signals->first() ?? 'none');
    }

    public function closingChannelLabel(string $channelKey): string
    {
        $label = config("revenue-ops.channels.{$channelKey}.label");

        return is_string($label) && $label !== ''
            ? $label
            : Str::of($channelKey === '' ? 'none' : $channelKey)->replace('_', ' ')->title()->value();
    }

    public function closingChannelCopy(string $channelKey): string
    {
        $copy = config("revenue-ops.channels.{$channelKey}.copy");

        return is_string($copy) && $copy !== ''
            ? $copy
            : 'Sin lectura adicional para este canal.';
    }

    private function hasPublishedOffer(Tenant $tenant): bool
    {
        return (int) ($tenant->products_total ?? 0) > 0 || (int) ($tenant->services_total ?? 0) > 0;
    }

    private function hasDirectChannel(Tenant $tenant): bool
    {
        return filled($tenant->whatsapp_number) || filled($tenant->phone) || filled($tenant->website_url);
    }

    private function needsActivationPush(Tenant $tenant): bool
    {
        $crmStatus = (string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW);
        $daysSinceCreated = $tenant->created_at instanceof Carbon ? (int) $tenant->created_at->diffInDays(now()) : 999;

        return in_array($crmStatus, [Tenant::CRM_STATUS_NEW, Tenant::CRM_STATUS_ONBOARDING], true)
            || (! $this->hasPublishedOffer($tenant) && $daysSinceCreated <= 30);
    }

    private function isExpansionReady(Tenant $tenant): bool
    {
        $crmStatus = (string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW);
        $daysSinceCreated = $tenant->created_at instanceof Carbon ? (int) $tenant->created_at->diffInDays(now()) : 0;

        return in_array($crmStatus, [Tenant::CRM_STATUS_ACTIVE, Tenant::CRM_STATUS_UPSELL], true)
            && ! $tenant->is_pro
            && $this->hasPublishedOffer($tenant)
            && $this->hasDirectChannel($tenant)
            && $daysSinceCreated >= 7;
    }

    private function isRenewalMotionCandidate(Tenant $tenant): bool
    {
        $crmStatus = (string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW);

        return $crmStatus === Tenant::CRM_STATUS_RENEWAL
            || ($tenant->is_pro && $tenant->subscription_ends_at instanceof Carbon && $tenant->subscription_ends_at->lte(now()->addDays(7)));
    }

    private function isActivatedTenant(Tenant $tenant): bool
    {
        return in_array((string) ($tenant->getRawOriginal('crm_status') ?? Tenant::CRM_STATUS_NEW), [
            Tenant::CRM_STATUS_ACTIVE,
            Tenant::CRM_STATUS_UPSELL,
            Tenant::CRM_STATUS_RENEWAL,
        ], true);
    }
}
