<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class WelcomeController extends Controller
{
    public function index()
    {
        // 1. Metrics: Total active businesses
        // Cached for 1 hour to avoid counting on every request
        $metrics = Cache::remember('welcome_metrics', 3600, function () {
            return [
                'total_tenants' => Tenant::where('status', 'approved')
                    ->where('is_active', true)
                    ->count(),
            ];
        });

        // 2. Featured Categories: Top 6 by active tenant count
        // Cached for 1 hour
        $categories = Cache::remember('welcome_categories', 3600, function () {
            return Category::where('is_active', true)
                ->withCount(['tenants' => function ($q) {
                    $q->where('status', 'approved')->where('is_active', true);
                }])
                ->orderByDesc('tenants_count')
                ->take(6)
                ->get()
                ->map(fn ($cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'icon' => $cat->icon,
                    'color' => $cat->color,
                    'count' => $cat->tenants_count,
                ]);
        });

        // 3. Featured Tenants (Heroes): PRO + Active + Has Banner
        // Random 3 for the homepage showcase
        $featuredTenants = Tenant::where('status', 'approved')
            ->where('is_active', true)
            ->where('is_pro', true)
            ->whereNotNull('banner_path') // Only show those with nice covers
            ->with(['city:id,name', 'category:id,name'])
            ->inRandomOrder()
            ->take(3)
            ->get()
            ->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'logo' => $tenant->logo_path,
                    'cover' => $tenant->banner_path,
                    'blur_hash' => $tenant->blur_hash,
                    'city' => $tenant->city->name ?? 'Honduras',
                    'category' => $tenant->category->name ?? 'Comercio',
                    'description' => $tenant->description,
                ];
            });

        return Inertia::render('Welcome', [
            'metrics' => $metrics,
            'categories' => $categories,
            'featuredTenants' => $featuredTenants,
        ]);
    }
}
