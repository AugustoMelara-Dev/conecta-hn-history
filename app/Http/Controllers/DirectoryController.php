<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DirectoryController extends Controller
{
    public function index(Request $request)
    {
        $city = $request->input('city', 'Tegucigalpa');

        $query = Tenant::query()
            ->select('id', 'name', 'slug', 'logo_path', 'banner_path', 'blur_hash', 'city', 'is_pro', 'category_id', 'description')
            ->where('status', 'approved');

        // Location Filter (Strict)
        // If a city is selected in the picker (session/cookie), it overrides the default
        // But if passed via query param (e.g. from landing), use that.
        // Logic: Query Param > Session > Default
        $activeCityId = session('active_city_id');
        if ($request->has('city')) {
            $query->where('city', $request->input('city'));
        } elseif ($activeCityId) {
            $cityModel = \App\Models\City::find($activeCityId);
            if ($cityModel) {
                $query->where('city', $cityModel->name);
            }
        }

        // Category Filter
        if ($request->has('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->input('category'));
            });
        }

        // Search Filter
        if ($request->has('search')) {
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
        if ($request->has('rating')) {
            $minRating = (int) $request->input('rating');
            $query->whereHas('reviews')
                ->withAvg('reviews', 'rating')
                ->having('reviews_avg_rating', '>=', $minRating);
        }

        // Eager Load Relationships
        $query->with(['category', 'reviews', 'products' => function ($q) {
            $q->select('id', 'tenant_id', 'name', 'image_path', 'blur_hash', 'price')
                ->take(4);
        }]);

        // Prioritize PRO tenants
        $tenants = $query->orderByDesc('is_pro')
            ->orderBy('name')
            ->get();

        // Get all categories for pills (Cached)
        // DYNAMIC DATA: Fetch only active categories
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
        if (session('active_city_id')) {
            $activeCity = \App\Models\City::find(session('active_city_id'));
        }

        return Inertia::render('Directory/Index', [
            'tenants' => $tenants,
            'categories' => $categories,
            'cities' => $cities,
            'activeCity' => $activeCity,
            'filters' => [
                'category' => $request->input('category'),
                'city' => $request->input('city'),
                'search' => $request->input('search'),
                'rating' => $request->input('rating'),
            ],
        ]);
    }
}
