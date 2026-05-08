<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ExploreController - Marketplace Discovery Page
 *
 * LUXURY ENTRANCE STRATEGY:
 * - Root (/) = B2B Sales Landing (WelcomeController)
 * - /explore = Marketplace Discovery (this controller)
 * - /@{slug} = Tenant Microsites (TenantMicrositeController)
 *
 * This controller powers the discovery engine where users browse and find businesses.
 */
final class ExploreController extends Controller
{
    /**
     * Display the marketplace discovery page.
     */
    public function __invoke(): Response
    {
        $data = Cache::remember("v".config('app.version')."_explore_data", 3600, function () {
            $tenantColumns = collect([
                'id', 'name', 'slug', 'description', 'cover_path', 'hero_image',
                'banner_path', 'logo_path', 'city_id', 'category_id', 'created_at',
            ])->filter(fn (string $column): bool => Schema::hasColumn('tenants', $column))->values()->all();
            $tenantRelations = [];

            if (Schema::hasTable('categories') && Schema::hasColumn('tenants', 'category_id')) {
                $categoryColumns = collect(['id', 'name', 'slug'])
                    ->filter(fn (string $column): bool => Schema::hasColumn('categories', $column))
                    ->values()
                    ->all();

                $tenantRelations[] = 'category:'.implode(',', $categoryColumns);
            }

            if (Schema::hasTable('cities') && Schema::hasColumn('tenants', 'city_id')) {
                $cityColumns = collect(['id', 'name', 'slug'])
                    ->filter(fn (string $column): bool => Schema::hasColumn('cities', $column))
                    ->values()
                    ->all();

                $tenantRelations[] = 'city:'.implode(',', $cityColumns);
            }

            return [
                'featuredTenants' => Tenant::query()
                    ->select($tenantColumns)
                    ->when(Schema::hasColumn('tenants', 'is_visible'), fn (Builder $query) => $query->where('is_visible', true))
                    ->when(Schema::hasColumn('tenants', 'is_active'), fn (Builder $query) => $query->where('is_active', true))
                    ->when(Schema::hasColumn('tenants', 'logo_path'), fn (Builder $query) => $query->whereNotNull('logo_path'))
                    ->with($tenantRelations)
                    ->when(
                        Schema::hasColumn('tenants', 'created_at'),
                        fn (Builder $query) => $query->latest(),
                        fn (Builder $query) => $query->orderBy('name')
                    )
                    ->limit(12)
                    ->get()
                    ->map(function (Tenant $t): array {
                        $attributes = $t->getAttributes();

                        return [
                            'id' => $t->id,
                            'name' => $t->name,
                            'slug' => $t->slug,
                            'cover_url' => $attributes['cover_path'] ?? $attributes['hero_image'] ?? $attributes['banner_path'] ?? null,
                            'logo_url' => $attributes['logo_path'] ?? null,
                            'category' => $t->relationLoaded('category') ? $t->category?->name : null,
                            'city' => $t->relationLoaded('city') ? $t->city?->name : null,
                            'description' => \Illuminate\Support\Str::limit($attributes['description'] ?? '', 80),
                        ];
                    }),

                'categories' => $this->exploreCategories(),

                'stats' => [
                    'total_tenants' => Tenant::query()
                        ->when(Schema::hasColumn('tenants', 'is_visible'), fn (Builder $query) => $query->where('is_visible', true))
                        ->when(Schema::hasColumn('tenants', 'is_active'), fn (Builder $query) => $query->where('is_active', true))
                        ->count(),
                    'total_cities' => $this->cityCount(),
                ],
            ];
        });

        return Inertia::render('Public/Home', $data);
    }

    private function exploreCategories()
    {
        if (! Schema::hasTable('categories')) {
            return collect();
        }

        $columns = collect(['id', 'name', 'slug', 'icon'])
            ->filter(fn (string $column): bool => Schema::hasColumn('categories', $column))
            ->values()
            ->all();

        return Category::query()
            ->select($columns)
            ->when(
                Schema::hasColumn('tenants', 'category_id'),
                fn (Builder $query) => $query->whereHas('tenants')->withCount('tenants')->orderByDesc('tenants_count'),
                fn (Builder $query) => $query->orderBy('name')
            )
            ->limit(8)
            ->get();
    }

    private function cityCount(): int
    {
        if (! Schema::hasTable('cities')) {
            return 0;
        }

        if (! Schema::hasColumn('tenants', 'city_id')) {
            return City::query()->count();
        }

        return City::has('tenants')->count();
    }
}
