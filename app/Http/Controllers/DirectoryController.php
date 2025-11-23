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
            ->select('id', 'name', 'slug', 'logo_path', 'blur_hash', 'city', 'is_pro', 'category_id', 'description')
            ->where('status', 'approved')
            ->where('city', $city) // STRICT city filter

            ->with(['category', 'reviews', 'products' => function($q) { 
                $q->select('id', 'tenant_id', 'name', 'image_path', 'blur_hash', 'price')
                    ->take(4); 
            }]); // Eager load with limit and blur_hash

        // Price filter removed as per requirements

        // Rating filter (simplified)
        if ($request->has('rating')) {
            $minRating = (int) $request->input('rating');
            $query->whereHas('reviews')
                ->withAvg('reviews', 'rating')
                ->having('reviews_avg_rating', '>=', $minRating);
        }

        // Prioritize PRO tenants
        $tenants = $query->orderByDesc('is_pro')
            ->orderBy('name')
            ->get();

        // Get all categories for reference
        // Get all categories for reference
        $categories = \Illuminate\Support\Facades\Cache::rememberForever('categories_list', function () {
            return \App\Models\Category::select('id', 'name', 'slug', 'icon', 'color')
                ->orderBy('name')
                ->get();
        });

        return Inertia::render('Directory/Index', [
            'tenants' => $tenants,
            'categories' => \App\Models\Category::select('id', 'name', 'slug')->orderBy('name')->get(),
            'cities' => \App\Models\City::select('id', 'name', 'slug')->orderBy('name')->get(),
            'activeCity' => session('active_city_id') ? \App\Models\City::find(session('active_city_id')) : null,
            'filters' => [
                'category' => $request->input('category'),
                'city' => $request->input('city'),
                'search' => $request->input('search'),
            ],
        ]);
    }
}
