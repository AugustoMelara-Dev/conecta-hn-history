<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Rubros;

use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Models\Tenant;
use Illuminate\Support\Str;

final class ConfigRubroManifestReader implements RubroManifestReaderInterface
{
    public function forTenant(Tenant $tenant): array
    {
        $rubroKey = (string) (($tenant->rubro_key ?? null) ?: ($tenant->business_segment_key ?: ''));

        if ($rubroKey !== '' && $this->has($rubroKey)) {
            return $this->withMetadata($rubroKey, $this->get($rubroKey));
        }

        return $this->forBusinessType((string) $tenant->business_type->value);
    }

    public function forBusinessType(string $businessType): array
    {
        $defaults = config('rubro-manifest.business_type_defaults', []);
        $fallback = (string) config('rubro-manifest.default_rubro', 'retail_general');
        $rubroKey = (string) ($defaults[$businessType] ?? $fallback);

        return $this->withMetadata($rubroKey, $this->get($rubroKey));
    }

    public function get(string $rubroKey): array
    {
        $rubros = config('rubro-manifest.rubros', []);
        $fallback = (string) config('rubro-manifest.default_rubro', 'retail_general');

        $manifest = $rubros[$rubroKey] ?? $rubros[$fallback] ?? [];

        return is_array($manifest) ? $manifest : [];
    }

    public function capabilitiesForTenant(Tenant $tenant): array
    {
        $manifest = $this->forTenant($tenant);
        $always = config('rubro-manifest.base_capabilities', []);
        $included = data_get($manifest, 'capabilities.include', []);
        $excluded = data_get($manifest, 'capabilities.exclude', []);

        $capabilities = array_values(array_unique(array_merge(
            is_array($always) ? $always : [],
            is_array($included) ? $included : [],
        )));

        if (is_array($excluded) && $excluded !== []) {
            $capabilities = array_values(array_diff($capabilities, $excluded));
        }

        return $capabilities;
    }

    public function registrationOptions(): array
    {
        return $this->rubros()
            ->map(function (array $manifest, string $key): array {
                return [
                    'value' => (string) data_get($manifest, 'business_type', $key),
                    'rubro_key' => $key,
                    'label' => (string) data_get($manifest, 'presentation.label', Str::of($key)->replace('_', ' ')->title()),
                    'description' => (string) data_get($manifest, 'presentation.description', ''),
                    'examples' => array_values((array) data_get($manifest, 'presentation.examples', [])),
                    'capabilities' => $this->capabilitiesForManifest($manifest),
                ];
            })
            ->values()
            ->all();
    }

    public function categorySuggestions(): array
    {
        return $this->rubros()
            ->flatMap(function (array $manifest): array {
                $businessType = (string) data_get($manifest, 'business_type', '');

                return collect((array) data_get($manifest, 'registration.suggested_categories', []))
                    ->mapWithKeys(static fn (string $slug): array => [$slug => $businessType])
                    ->all();
            })
            ->all();
    }

    public function segmentProfiles(): array
    {
        return $this->rubros()
            ->map(function (array $manifest, string $key): array {
                return [
                    'key' => $key,
                    'businessType' => (string) data_get($manifest, 'business_type', ''),
                    'label' => (string) data_get($manifest, 'presentation.label', Str::of($key)->replace('_', ' ')->title()),
                    'teaser' => (string) data_get($manifest, 'presentation.teaser', data_get($manifest, 'presentation.description', '')),
                    'onboardingTitle' => (string) data_get($manifest, 'onboarding.title', 'Ruta sugerida para tu panel'),
                    'onboardingCopy' => (string) data_get($manifest, 'onboarding.copy', ''),
                    'playbookTitle' => (string) data_get($manifest, 'onboarding.playbook_title', 'Playbook operativo'),
                    'categories' => array_values((array) data_get($manifest, 'registration.suggested_categories', [])),
                    'modules' => array_values((array) data_get($manifest, 'onboarding.recommended_modules', [])),
                ];
            })
            ->values()
            ->all();
    }

    public function featureCapabilityMap(): array
    {
        $result = [];

        foreach ($this->rubros() as $manifest) {
            $businessType = (string) data_get($manifest, 'business_type', '');
            if ($businessType === '') {
                continue;
            }

            $capabilities = $this->capabilitiesForManifest($manifest);
            sort($capabilities);

            $result[$businessType] = ['capabilities' => $capabilities];
        }

        return $result;
    }

    public function onboardingForTenant(Tenant $tenant): array
    {
        return (array) data_get($this->forTenant($tenant), 'onboarding', []);
    }

    public function dynamicFormsForTenant(Tenant $tenant): array
    {
        return (array) data_get($this->forTenant($tenant), 'dynamic_forms', []);
    }

    public function allowedBusinessTypes(): array
    {
        return $this->rubros()
            ->map(static fn (array $manifest): string => (string) data_get($manifest, 'business_type', ''))
            ->filter(static fn (string $businessType): bool => $businessType !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function defaultBusinessType(): string
    {
        $defaultRubro = (string) config('rubro-manifest.default_rubro', 'retail_general');
        $businessType = (string) data_get($this->get($defaultRubro), 'business_type', '');

        return $businessType !== '' ? $businessType : 'retail';
    }

    public function hasModule(Tenant $tenant, string $module, string $surface = 'business_app'): bool
    {
        $modules = (array) data_get($this->forTenant($tenant), "modules.{$surface}", []);

        return in_array($module, array_map('strval', $modules), true);
    }

    public function version(): string
    {
        return (string) config('rubro-manifest.version', 'rubro-manifest-v1');
    }

    private function has(string $rubroKey): bool
    {
        return array_key_exists($rubroKey, config('rubro-manifest.rubros', []));
    }

    /**
     * @return \Illuminate\Support\Collection<string, array<string, mixed>>
     */
    private function rubros(): \Illuminate\Support\Collection
    {
        return collect(config('rubro-manifest.rubros', []))
            ->filter(static fn (mixed $manifest): bool => is_array($manifest));
    }

    /**
     * @param array<string, mixed> $manifest
     * @return list<string>
     */
    private function capabilitiesForManifest(array $manifest): array
    {
        $always = array_values((array) config('rubro-manifest.base_capabilities', []));
        $included = array_values((array) data_get($manifest, 'capabilities.include', []));
        $excluded = array_values((array) data_get($manifest, 'capabilities.exclude', []));
        $capabilities = array_values(array_unique(array_merge($always, $included)));

        if ($excluded !== []) {
            $capabilities = array_values(array_diff($capabilities, $excluded));
        }

        return $capabilities;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    private function withMetadata(string $rubroKey, array $manifest): array
    {
        return array_replace_recursive($manifest, [
            'rubro_key' => $rubroKey,
            'manifest_version' => $this->version(),
        ]);
    }
}
