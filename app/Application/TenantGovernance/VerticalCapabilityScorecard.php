<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance;

use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Domain\TenantGovernance\Enums\Capability;

final readonly class VerticalCapabilityScorecard
{
    public function __construct(
        private RubroManifestReaderInterface $manifestReader,
    ) {}

    /**
     * @return array<string, array{business_type:string, capabilities:list<string>, readiness:array<string, array{status:string, reason:string}>}>
     */
    public function byBusinessType(): array
    {
        $scorecard = [];

        foreach ($this->manifestReader->allowedBusinessTypes() as $businessType) {
            $manifest = $this->manifestReader->forBusinessType($businessType);
            $capabilities = $this->capabilities($manifest);
            $businessModules = $this->stringList(data_get($manifest, 'modules.business_app', []));
            $crmTemplates = $this->stringList(data_get($manifest, 'crm.templates', []));
            $crmSegments = $this->stringList(data_get($manifest, 'crm.preferred_segments', []));
            $growthJourneys = array_values((array) data_get($manifest, 'growth_center.journeys', []));

            $scorecard[$businessType] = [
                'business_type' => $businessType,
                'capabilities' => $capabilities,
                'readiness' => [
                    'billing' => $this->billingStatus($capabilities),
                    'operations' => $this->operationsStatus($capabilities),
                    'marketing' => $this->marketingStatus($businessModules, $crmTemplates, $growthJourneys),
                    'loyalty' => $this->loyaltyStatus($businessModules, $crmSegments, $growthJourneys),
                    'ai_content' => $this->capabilityStatus($capabilities, Capability::AI_CONTENT->value),
                    'analytics' => $this->analyticsStatus($capabilities, $growthJourneys),
                    'experience' => $this->experienceStatus(),
                ],
            ];
        }

        return $scorecard;
    }

    /**
     * @param  list<string>  $capabilities
     * @return array{status:string, reason:string}
     */
    private function billingStatus(array $capabilities): array
    {
        if (! in_array(Capability::PAYMENTS_BASIC->value, $capabilities, true)) {
            return ['status' => 'missing', 'reason' => 'Payments capability is not active in RubroManifest.'];
        }

        if (strtolower((string) config('services.payment.provider', 'generic')) === 'stripe') {
            return ['status' => 'ready', 'reason' => 'Payments capability is active and Stripe provider is selected.'];
        }

        return ['status' => 'partial', 'reason' => 'Payments capability exists, but production provider remains manual/generic.'];
    }

    /**
     * @param  list<string>  $capabilities
     * @return array{status:string, reason:string}
     */
    private function operationsStatus(array $capabilities): array
    {
        $hasInventory = in_array(Capability::INVENTORY_TRACKING->value, $capabilities, true)
            && in_array(Capability::STOCK_ALERTS->value, $capabilities, true);
        $hasFoodOps = in_array(Capability::KITCHEN_DISPLAY->value, $capabilities, true);
        $hasServiceOps = in_array(Capability::STAFF_CALENDAR->value, $capabilities, true)
            || in_array(Capability::APPOINTMENT_BOOKING->value, $capabilities, true);

        if ($hasInventory && ($hasFoodOps || $hasServiceOps)) {
            return ['status' => 'ready', 'reason' => 'Inventory plus vertical operations capabilities are active.'];
        }

        if ($hasInventory || $hasFoodOps || $hasServiceOps) {
            return ['status' => 'partial', 'reason' => 'Some operational capabilities exist, but the vertical workflow is incomplete.'];
        }

        return ['status' => 'missing', 'reason' => 'No inventory, kitchen or scheduling capabilities are active.'];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    private function capabilities(array $manifest): array
    {
        $base = $this->stringList(config('rubro-manifest.base_capabilities', []));
        $included = $this->stringList(data_get($manifest, 'capabilities.include', []));
        $excluded = $this->stringList(data_get($manifest, 'capabilities.exclude', []));

        return array_values(array_diff(array_unique(array_merge($base, $included)), $excluded));
    }

    /**
     * @param  list<string>  $modules
     * @param  list<string>  $templates
     * @param  list<array<string, mixed>>  $journeys
     * @return array{status:string, reason:string}
     */
    private function marketingStatus(array $modules, array $templates, array $journeys): array
    {
        if (in_array('campaigns', $modules, true) && $templates !== [] && $journeys !== []) {
            return ['status' => 'ready', 'reason' => 'RubroManifest exposes campaigns, CRM templates and growth journeys.'];
        }

        if (in_array('campaigns', $modules, true) || $templates !== [] || $journeys !== []) {
            return ['status' => 'partial', 'reason' => 'Marketing is present but not fully wired across modules, templates and journeys.'];
        }

        return ['status' => 'missing', 'reason' => 'No marketing module, CRM templates or growth journeys in manifest.'];
    }

    /**
     * @param  list<string>  $modules
     * @param  list<string>  $segments
     * @param  list<array<string, mixed>>  $journeys
     * @return array{status:string, reason:string}
     */
    private function loyaltyStatus(array $modules, array $segments, array $journeys): array
    {
        if (in_array('loyalty', $modules, true)) {
            return ['status' => 'ready', 'reason' => 'Manifest exposes a dedicated loyalty module.'];
        }

        $hasRetentionSignals = array_intersect($segments, ['repeat', 'reactivation', 'vip']) !== []
            || collect($journeys)->contains(
                static fn (array $journey): bool => str_contains(strtolower((string) ($journey['title'] ?? '')), 'recompra')
            );

        if ($hasRetentionSignals) {
            return ['status' => 'partial', 'reason' => 'Retention signals exist, but no dedicated loyalty module is exposed.'];
        }

        return ['status' => 'missing', 'reason' => 'No loyalty module or retention journey signals in manifest.'];
    }

    /**
     * @param  list<string>  $capabilities
     * @return array{status:string, reason:string}
     */
    private function capabilityStatus(array $capabilities, string $capability): array
    {
        if (in_array($capability, $capabilities, true)) {
            return ['status' => 'ready', 'reason' => "Capability {$capability} is active in RubroManifest."];
        }

        return ['status' => 'missing', 'reason' => "Capability {$capability} is not active in RubroManifest."];
    }

    /**
     * @param  list<string>  $capabilities
     * @param  list<array<string, mixed>>  $journeys
     * @return array{status:string, reason:string}
     */
    private function analyticsStatus(array $capabilities, array $journeys): array
    {
        if (in_array(Capability::ANALYTICS_BASIC->value, $capabilities, true) && $journeys !== []) {
            return ['status' => 'ready', 'reason' => 'Analytics capability and growth journey signals are present.'];
        }

        if (in_array(Capability::ANALYTICS_BASIC->value, $capabilities, true)) {
            return ['status' => 'partial', 'reason' => 'Analytics tracking exists, but advanced journey signals are incomplete.'];
        }

        return ['status' => 'missing', 'reason' => 'Analytics capability is not active in RubroManifest.'];
    }

    /**
     * @return array{status:string, reason:string}
     */
    private function experienceStatus(): array
    {
        return ['status' => 'partial', 'reason' => 'PWA assets and Vite prefetch exist; offline mode, i18n and Storybook remain Phase 2.'];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->map(static fn (mixed $item): string => (string) $item)
            ->filter(static fn (string $item): bool => $item !== '')
            ->values()
            ->all();
    }
}
