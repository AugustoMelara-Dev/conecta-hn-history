<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DirectoryController extends Controller
{
    public function index(Request $request)
    {
        $activeCityId = session('active_city_id');

        $query = Tenant::query()
            ->select('id', 'name', 'slug', 'logo_path', 'banner_path', 'blur_hash', 'city_id', 'is_pro', 'category_id', 'description')
            ->where('status', 'approved')
            ->where('is_active', true);

        // Location Filter (Strict)
        // Logic: Query Param > Session > Default
        if ($request->filled('city')) {
            $query->whereHas('city', function ($q) use ($request) {
                $q->where('name', $request->input('city'));
            });
        } elseif ($activeCityId) {
            // Optimization: Filter directly by ID if we have it from session
            $query->where('city_id', $activeCityId);
        }

        // Category Filter
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->input('category'));
            });
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('products', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
            });
        }

        // Rating Filter
        if ($request->filled('rating')) {
            $minRating = (int) $request->input('rating');
            $query->whereHas('reviews')
                ->withAvg('reviews', 'rating')
                ->having('reviews_avg_rating', '>=', $minRating);
        }

        // Eager Load Relationships (Added city)
        $query->with(['category', 'city', 'reviews', 'products' => function ($q) {
            $q->select('id', 'tenant_id', 'name', 'image_path', 'blur_hash', 'price')
                ->take(4);
        }]);

        // Prioritize PRO tenants & Pagination
        $tenants = $query->orderByDesc('is_pro')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        // Get all categories for pills (Cached)
        $categories = \Illuminate\Support\Facades\Cache::rememberForever('categories_list', function () {
            return \App\Models\Category::where('is_active', true)
                ->select('id', 'name', 'slug', 'icon', 'color')
                ->orderBy('name')
                ->get();
        });

        // Get all cities for picker (Cached)
        $cities = \Illuminate\Support\Facades\Cache::rememberForever('cities_list', function () {
            return \App\Models\City::select('id', 'name', 'slug')
                ->orderBy('name')
                ->get();
        });

        // Get active city safely
        $activeCity = null;
        if ($activeCityId) {
            $activeCity = \App\Models\City::find($activeCityId);
        }

        return Inertia::render('Directory/Index', [
            'tenants' => $tenants,
            'categories' => $categories,
            'cities' => $cities,
            'activeCity' => $activeCity,
            'filters' => $request->only(['search', 'category', 'category_id', 'rating', 'city']),
        ]);
    }
}
