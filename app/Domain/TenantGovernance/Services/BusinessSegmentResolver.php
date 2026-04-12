<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\Tenant;
use Illuminate\Support\Str;

final class BusinessSegmentResolver
{
    public function resolveForTenant(Tenant $tenant): string
    {
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
        /** @var array<string, array<int, string>> $segmentKeywordMap */
        $segmentKeywordMap = config('storefront-segments.keyword_map', []);
        /** @var array<string, string> $slugOverrides */
        $slugOverrides = config('storefront-segments.slug_overrides', []);
        /** @var array<string, mixed> $verticalConfig */
        $verticalConfig = config('storefront-verticals', []);
        /** @var array<string, array<int, string>> $keywordMap */
        $keywordMap = array_merge($segmentKeywordMap, $verticalConfig['keyword_map'] ?? []);
        /** @var array<string, string> $categoryMap */
        $categoryMap = $verticalConfig['category_map'] ?? [];
        /** @var array<string, string> $defaults */
        $defaults = $verticalConfig['defaults'] ?? [];

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

        foreach ($keywordMap as $segmentKey => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($haystack, Str::lower(Str::ascii($keyword)))) {
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
     * @return array<string, mixed>
     */
    public function internalProfile(string $segmentKey, string $businessType): array
    {
        /** @var array<string, mixed> $profiles */
        $profiles = config('business-segments.profiles', []);
        /** @var array<string, string> $defaults */
        $defaults = config('business-segments.defaults', []);

        $fallbackKey = $defaults[$businessType] ?? 'retail_general';

        /** @var array<string, mixed> $fallbackProfile */
        $fallbackProfile = $profiles[$fallbackKey] ?? [];

        if (isset($profiles[$segmentKey]) && is_array($profiles[$segmentKey])) {
            /** @var array<string, mixed> $profile */
            $profile = $profiles[$segmentKey];

            return array_replace_recursive($fallbackProfile, $profile);
        }

        return $fallbackProfile;
    }
}
