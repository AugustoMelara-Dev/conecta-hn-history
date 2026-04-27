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

    public function rubroLabels(): array
    {
        return $this->rubros()
            ->mapWithKeys(fn (array $manifest, string $key): array => [
                $key => (string) data_get($manifest, 'presentation.label', Str::of($key)->replace('_', ' ')->title()),
            ])
            ->all();
    }

    public function labelForRubroKey(?string $rubroKey): string
    {
        $key = trim((string) $rubroKey);

        if ($key === '') {
            return 'General';
        }

        $labels = $this->rubroLabels();

        return $labels[$key] ?? Str::of($key)->replace('_', ' ')->title()->value();
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

    public function businessAppForTenant(Tenant $tenant): array
    {
        return (array) data_get($this->forTenant($tenant), 'business_app', []);
    }

    public function storefrontProfileForTenant(Tenant $tenant): array
    {
        $manifest = $this->forTenant($tenant);

        return $this->storefrontProfileFromManifest($manifest);
    }

    public function storefrontProfileForRubroKey(string $rubroKey, Tenant $tenant): array
    {
        if ($this->has($rubroKey)) {
            return $this->storefrontProfileFromManifest(
                $this->withMetadata($rubroKey, $this->get($rubroKey))
            );
        }

        $baseProfile = $this->storefrontProfileForTenant($tenant);
        $verticalProfile = $this->manifestStorefrontProfile($rubroKey);
        $verticalPlaybook = $this->manifestStorefrontPlaybook($rubroKey);

        return array_replace(
            $baseProfile,
            array_intersect_key($verticalProfile, $this->storefrontProfileKeys()),
            array_intersect_key($verticalPlaybook, $this->storefrontProfileKeys()),
            [
                'vertical_key' => $rubroKey,
                'vertical_label' => (string) ($verticalProfile['vertical_label'] ?? $baseProfile['vertical_label'] ?? $baseProfile['label'] ?? Str::of($rubroKey)->replace('_', ' ')->title()),
                'trust_points' => array_values((array) ($verticalProfile['trust_points'] ?? $baseProfile['trust_points'] ?? [])),
                'specialized_blocks' => array_values((array) ($verticalProfile['specialized_blocks'] ?? $baseProfile['specialized_blocks'] ?? [])),
                'focus_cards' => array_values((array) ($verticalProfile['focus_cards'] ?? $baseProfile['focus_cards'] ?? [])),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function storefrontProfileFromManifest(array $manifest): array
    {
        $presentation = (array) data_get($manifest, 'presentation', []);
        $businessApp = (array) data_get($manifest, 'business_app', []);
        $onboarding = (array) data_get($manifest, 'onboarding', []);
        $modules = array_values((array) data_get($manifest, 'modules.user_app', []));
        $businessType = (string) data_get($manifest, 'business_type', 'retail');
        $label = (string) data_get($presentation, 'label', $this->labelForRubroKey((string) data_get($manifest, 'rubro_key')));
        $description = (string) data_get($presentation, 'description', 'Negocio local en Honduras.');

        return array_replace_recursive([
            'vertical_key' => (string) data_get($manifest, 'rubro_key', $businessType),
            'vertical_label' => $label,
            'label' => $label,
            'hero_title' => (string) data_get($presentation, 'teaser', data_get($businessApp, 'dashboard_title', $label)),
            'hero_copy' => $description,
            'offer_title' => (string) data_get($businessApp, 'workspace_title', 'Oferta principal'),
            'offer_copy' => (string) data_get($businessApp, 'workspace_copy', $description),
            'catalog_title' => $this->catalogTitleForModules($modules, $businessType),
            'catalog_copy' => $this->catalogCopyForModules($modules, $businessType),
            'primary_cta_label' => in_array('booking', $modules, true) && ! in_array('checkout', $modules, true) ? 'Reservar ahora' : 'Ver catalogo',
            'secondary_cta_label' => 'Hablar por WhatsApp',
            'mobile_tab_label' => in_array('booking', $modules, true) && ! in_array('checkout', $modules, true) ? 'Servicios' : 'Catalogo',
            'footer_copy' => (string) data_get($businessApp, 'playbook_copy', $description),
            'journey_title' => (string) data_get($onboarding, 'playbook_title', 'Ruta recomendada'),
            'journey_copy' => (string) data_get($onboarding, 'copy', $description),
            'journey_steps' => $this->journeyStepsForManifest($manifest),
            'growth_title' => (string) data_get($businessApp, 'dashboard_title', 'Crecimiento del negocio'),
            'growth_copy' => (string) data_get($businessApp, 'dashboard_copy', $description),
            'growth_cards' => $this->growthCardsForManifest($manifest),
            'trust_points' => array_values((array) data_get($manifest, 'storefront.trust_points', [])),
            'specialized_blocks' => array_values((array) data_get($manifest, 'storefront.specialized_blocks', [])),
            'focus_cards' => array_values((array) data_get($manifest, 'storefront.focus_cards', [])),
        ], (array) data_get($manifest, 'storefront.profile', []));
    }

    public function crmForTenant(Tenant $tenant): array
    {
        return (array) data_get($this->forTenant($tenant), 'crm', []);
    }

    public function growthProfileForTenant(Tenant $tenant): array
    {
        return (array) data_get($this->forTenant($tenant), 'growth_center', []);
    }

    public function allowedDynamicEntityKeysForTenant(Tenant $tenant): array
    {
        return collect((array) data_get($this->dynamicFormsForTenant($tenant), 'entities', []))
            ->map(static function (mixed $definition): ?string {
                if (is_array($definition)) {
                    $entityKey = (string) ($definition['entity_key'] ?? '');

                    return $entityKey !== '' ? $entityKey : null;
                }

                return is_string($definition) && $definition !== '' ? $definition : null;
            })
            ->filter()
            ->values()
            ->all();
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

    /**
     * @param list<string> $modules
     */
    private function catalogTitleForModules(array $modules, string $businessType): string
    {
        if (in_array('booking', $modules, true) && ! in_array('checkout', $modules, true)) {
            return 'Servicios disponibles';
        }

        return $businessType === 'food' ? 'Menu disponible' : 'Catalogo publico';
    }

    /**
     * @param list<string> $modules
     */
    private function catalogCopyForModules(array $modules, string $businessType): string
    {
        if (in_array('booking', $modules, true) && ! in_array('checkout', $modules, true)) {
            return 'Consulta servicios, duracion y disponibilidad antes de reservar.';
        }

        return $businessType === 'food'
            ? 'Consulta platos, combos y bebidas visibles en este momento.'
            : 'Revisa productos visibles, precios y opciones de contacto rapido.';
    }

    /**
     * @param array<string, mixed> $manifest
     * @return list<array<string, string>>
     */
    private function journeyStepsForManifest(array $manifest): array
    {
        $explicit = collect((array) data_get($manifest, 'onboarding.playbook_steps', []))
            ->filter(static fn (mixed $step): bool => is_array($step))
            ->map(static fn (array $step, int $index): array => [
                'eyebrow' => (string) ($step['eyebrow'] ?? 'Paso '.($index + 1)),
                'title' => (string) ($step['title'] ?? ''),
                'copy' => (string) ($step['copy'] ?? ''),
            ])
            ->filter(static fn (array $step): bool => $step['title'] !== '')
            ->values()
            ->all();

        if ($explicit !== []) {
            return $explicit;
        }

        return collect((array) data_get($manifest, 'onboarding.recommended_modules', []))
            ->map(fn (mixed $module, int $index): array => [
                'title' => Str::of((string) $module)->replace('_', ' ')->title()->value(),
                'copy' => $index === 0
                    ? 'Activa este frente primero para que el negocio tenga base operativa.'
                    : 'Completa este modulo para conectar experiencia publica, gestion interna y seguimiento.',
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $manifest
     * @return list<array<string, string>>
     */
    private function growthCardsForManifest(array $manifest): array
    {
        $explicit = collect((array) data_get($manifest, 'growth_center.journeys', []))
            ->merge((array) data_get($manifest, 'growth_center.cadence_playbooks', []))
            ->filter(static fn (mixed $card): bool => is_array($card))
            ->take(4)
            ->map(static fn (array $card): array => [
                'eyebrow' => (string) ($card['eyebrow'] ?? $card['channel'] ?? 'Growth'),
                'title' => (string) ($card['title'] ?? ''),
                'copy' => (string) ($card['copy'] ?? ''),
                'cta_label' => (string) ($card['cta_label'] ?? 'Abrir frente'),
                'cta_target' => (string) ($card['cta_target'] ?? '#workspace'),
            ])
            ->filter(static fn (array $card): bool => $card['title'] !== '')
            ->values()
            ->all();

        if ($explicit !== []) {
            return $explicit;
        }

        return collect((array) data_get($manifest, 'modules.business_app', []))
            ->take(4)
            ->map(fn (mixed $module): array => [
                'title' => Str::of((string) $module)->replace('_', ' ')->title()->value(),
                'copy' => 'Modulo recomendado por el RubroManifest para este tipo de negocio.',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, true>
     */
    private function storefrontProfileKeys(): array
    {
        return [
            'label' => true,
            'hero_title' => true,
            'hero_copy' => true,
            'offer_title' => true,
            'offer_copy' => true,
            'catalog_title' => true,
            'catalog_copy' => true,
            'primary_cta_label' => true,
            'secondary_cta_label' => true,
            'mobile_tab_label' => true,
            'footer_copy' => true,
            'journey_title' => true,
            'journey_copy' => true,
            'journey_steps' => true,
            'growth_title' => true,
            'growth_copy' => true,
            'growth_cards' => true,
            'catalog_panel_title' => true,
            'catalog_panel_copy' => true,
            'service_panel_title' => true,
            'service_panel_copy' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function manifestStorefrontProfile(string $rubroKey): array
    {
        $profiles = config('rubro-manifest.storefront.profiles', []);

        return isset($profiles[$rubroKey]) && is_array($profiles[$rubroKey])
            ? $profiles[$rubroKey]
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function manifestStorefrontPlaybook(string $rubroKey): array
    {
        $playbooks = config('rubro-manifest.storefront.playbooks', []);

        return isset($playbooks[$rubroKey]) && is_array($playbooks[$rubroKey])
            ? $playbooks[$rubroKey]
            : [];
    }
}
