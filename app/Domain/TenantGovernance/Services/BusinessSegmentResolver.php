<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Models\Tenant;
use Illuminate\Support\Str;

final class BusinessSegmentResolver
{
    public function __construct(
        private readonly RubroManifestReaderInterface $rubroManifest,
    ) {}

    public function resolveForTenant(Tenant $tenant): string
    {
        if (filled($tenant->rubro_key)) {
            return (string) $tenant->rubro_key;
        }

        $category = $tenant->category;

        return $this->resolve(
            businessType: $tenant->business_type->value,
            name: $tenant->name,
            description: $tenant->description,
            about: $tenant->about,
            slug: $tenant->slug,
            categorySlug: $category instanceof \App\Models\Category ? $category->slug : null,
            categoryName: $category instanceof \App\Models\Category ? $category->name : null,
        );
    }

    public function resolve(
        string $businessType,
        ?string $name = null,
        ?string $description = null,
        ?string $about = null,
        ?string $slug = null,
        ?string $categorySlug = null,
        ?string $categoryName = null,
    ): string {
        /** @var array<string, string> $slugOverrides */
        $slugOverrides = config('rubro-manifest.resolution.slug_overrides', []);
        /** @var array<string, array<int, string>> $keywordMap */
        $keywordMap = config('rubro-manifest.resolution.keyword_map', []);
        /** @var array<string, string> $categoryMap */
        $categoryMap = config('rubro-manifest.resolution.category_map', []);
        /** @var array<string, string> $defaults */
        $defaults = config('rubro-manifest.resolution.business_type_defaults', []);

        $normalizedSlug = Str::lower(Str::ascii((string) $slug));
        if ($normalizedSlug !== '' && isset($slugOverrides[$normalizedSlug])) {
            return $slugOverrides[$normalizedSlug];
        }

        $haystack = Str::lower(Str::ascii(implode(' ', array_filter([
            $name,
            $description,
            $about,
            $categoryName,
            $categorySlug,
        ]))));

        foreach ($this->orderedKeywordMap($keywordMap) as $segmentKey => $keywords) {
            foreach ($keywords as $keyword) {
                if ($this->keywordMatches($haystack, (string) $keyword)) {
                    return $segmentKey;
                }
            }
        }

        $normalizedCategorySlug = (string) ($categorySlug ?? '');

        if ($normalizedCategorySlug !== '' && isset($categoryMap[$normalizedCategorySlug])) {
            return $categoryMap[$normalizedCategorySlug];
        }

        return $defaults[$businessType] ?? 'retail_general';
    }

    /**
     * @param  array<string, array<int, string>>  $keywordMap
     * @return array<string, array<int, string>>
     */
    private function orderedKeywordMap(array $keywordMap): array
    {
        $priority = [
            'corporate_law_firm',
            'dental_clinic',
            'financial_consultancy',
            'premium_barbershop',
            'spa_studio',
            'premium_phone_store',
            'hardware_store',
            'auto_parts_store',
            'coffee_shop',
            'casual_restaurant',
            'law_firm',
            'clinic',
            'barbershop',
            'cellphone_store',
            'workshop',
            'restaurant',
            'consultancy',
        ];

        $ordered = [];

        foreach ($priority as $segmentKey) {
            if (isset($keywordMap[$segmentKey])) {
                $ordered[$segmentKey] = $keywordMap[$segmentKey];
            }
        }

        foreach ($keywordMap as $segmentKey => $keywords) {
            if (! isset($ordered[$segmentKey])) {
                $ordered[$segmentKey] = $keywords;
            }
        }

        return $ordered;
    }

    private function keywordMatches(string $haystack, string $keyword): bool
    {
        $normalizedKeyword = Str::lower(Str::ascii($keyword));

        if ($normalizedKeyword === '') {
            return false;
        }

        if (strlen($normalizedKeyword) <= 3 && preg_match('/^[a-z0-9]+$/', $normalizedKeyword) === 1) {
            return preg_match('/\b'.preg_quote($normalizedKeyword, '/').'\b/', $haystack) === 1;
        }

        return Str::contains($haystack, $normalizedKeyword);
    }

    /**
     * @return array<string, mixed>
     */
    public function internalProfile(string $segmentKey, string $businessType): array
    {
        $manifest = $this->rubroManifest->get($segmentKey);
        if ($manifest !== []) {
            return [
                'key' => $segmentKey,
                'label' => (string) data_get($manifest, 'presentation.label', Str::of($segmentKey)->replace('_', ' ')->title()),
                'description' => (string) data_get($manifest, 'presentation.description', ''),
                'teaser' => (string) data_get($manifest, 'presentation.teaser', ''),
                'recommended_modules' => array_values((array) data_get($manifest, 'onboarding.recommended_modules', [])),
                'business_type' => (string) data_get($manifest, 'business_type', $businessType),
                'dashboard_title' => (string) data_get($manifest, 'business_app.dashboard_title', ''),
                'dashboard_copy' => (string) data_get($manifest, 'business_app.dashboard_copy', ''),
                'workspace_title' => (string) data_get($manifest, 'business_app.workspace_title', ''),
                'workspace_copy' => (string) data_get($manifest, 'business_app.workspace_copy', ''),
                'onboarding_title' => (string) data_get($manifest, 'onboarding.title', ''),
                'onboarding_copy' => (string) data_get($manifest, 'onboarding.copy', ''),
                'playbook_title' => (string) data_get($manifest, 'onboarding.playbook_title', ''),
                'playbook_copy' => (string) data_get($manifest, 'business_app.playbook_copy', ''),
                'playbook_steps' => $this->manifestPlaybookSteps($manifest),
                'health_checks' => $this->manifestHealthChecks($manifest),
                'source' => 'rubro_manifest',
            ];
        }

        $defaults = (array) config('rubro-manifest.business_type_defaults', []);
        $fallbackKey = (string) ($defaults[$businessType] ?? config('rubro-manifest.default_rubro', 'retail_general'));
        $fallbackManifest = $this->rubroManifest->get($fallbackKey);

        if ($fallbackManifest !== []) {
            return [
                'key' => $fallbackKey,
                'label' => (string) data_get($fallbackManifest, 'presentation.label', Str::of($fallbackKey)->replace('_', ' ')->title()),
                'description' => (string) data_get($fallbackManifest, 'presentation.description', ''),
                'teaser' => (string) data_get($fallbackManifest, 'presentation.teaser', ''),
                'recommended_modules' => array_values((array) data_get($fallbackManifest, 'onboarding.recommended_modules', [])),
                'business_type' => (string) data_get($fallbackManifest, 'business_type', $businessType),
                'dashboard_title' => (string) data_get($fallbackManifest, 'business_app.dashboard_title', ''),
                'dashboard_copy' => (string) data_get($fallbackManifest, 'business_app.dashboard_copy', ''),
                'workspace_title' => (string) data_get($fallbackManifest, 'business_app.workspace_title', ''),
                'workspace_copy' => (string) data_get($fallbackManifest, 'business_app.workspace_copy', ''),
                'onboarding_title' => (string) data_get($fallbackManifest, 'onboarding.title', ''),
                'onboarding_copy' => (string) data_get($fallbackManifest, 'onboarding.copy', ''),
                'playbook_title' => (string) data_get($fallbackManifest, 'onboarding.playbook_title', ''),
                'playbook_copy' => (string) data_get($fallbackManifest, 'business_app.playbook_copy', ''),
                'playbook_steps' => $this->manifestPlaybookSteps($fallbackManifest),
                'health_checks' => $this->manifestHealthChecks($fallbackManifest),
                'source' => 'rubro_manifest_fallback',
            ];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<array{title:string,copy:string}>
     */
    private function manifestPlaybookSteps(array $manifest): array
    {
        $explicit = collect((array) data_get($manifest, 'onboarding.playbook_steps', []))
            ->filter(static fn (mixed $step): bool => is_array($step))
            ->map(static fn (array $step): array => [
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
            ->map(static fn (mixed $module, int $index): array => [
                'title' => Str::of((string) $module)->replace('_', ' ')->title()->value(),
                'copy' => $index === 0
                    ? 'Activa este modulo primero para darle base comercial y operativa al negocio.'
                    : 'Completa este modulo para conectar mejor la experiencia publica con la operacion interna.',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<array<string, mixed>>
     */
    private function manifestHealthChecks(array $manifest): array
    {
        return collect((array) data_get($manifest, 'onboarding.health_checks', []))
            ->map(function (mixed $item, int $index): array {
                if (is_array($item)) {
                    return [
                        'title' => (string) ($item['title'] ?? Str::of((string) ($item['state'] ?? "check_{$index}"))->replace('_', ' ')->title()->value()),
                        'pending_copy' => (string) ($item['pending_copy'] ?? 'Completa este frente para destrabar el siguiente paso del negocio.'),
                        'complete_copy' => (string) ($item['complete_copy'] ?? 'Este frente ya esta cubierto para el rubro actual.'),
                        'weight' => (int) ($item['weight'] ?? 20),
                        'state' => (string) ($item['state'] ?? "manifest_check_{$index}"),
                    ];
                }

                $state = (string) $item;

                return [
                    'title' => Str::of($state)->replace('_', ' ')->title()->value(),
                    'pending_copy' => 'Completa este frente para destrabar el siguiente paso del negocio.',
                    'complete_copy' => 'Este frente ya esta cubierto para el rubro actual.',
                    'weight' => 20,
                    'state' => $state !== '' ? $state : "manifest_check_{$index}",
                ];
            })
            ->filter(static fn (array $item): bool => $item['title'] !== '')
            ->values()
            ->all();
    }
}
