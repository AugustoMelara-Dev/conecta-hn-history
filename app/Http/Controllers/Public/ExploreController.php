<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
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
            return [
                'featuredTenants' => Tenant::query()
                    ->where('is_visible', true)
                    ->whereNotNull('logo_path')
                    ->latest()
                    ->limit(12)
                    ->get()
                    ->map(fn (Tenant $t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'slug' => $t->slug,
                        'cover_url' => $t->cover_url,
                        'logo_url' => $t->logo_url,
                        'category' => $t->category?->name,
                        'city' => $t->city?->name,
                        'description' => \Illuminate\Support\Str::limit($t->description, 80),
                    ]),

                'categories' => Category::query()
                    ->withCount('tenants')
                    ->having('tenants_count', '>', 0)
                    ->orderByDesc('tenants_count')
                    ->limit(8)
                    ->get(['id', 'name', 'slug', 'icon']),

                'stats' => [
                    'total_tenants' => Tenant::where('is_visible', true)->count(),
                    'total_cities' => City::has('tenants')->count(),
                ],
            ];
        });

        return Inertia::render('Public/Home', $data);
    }
}
