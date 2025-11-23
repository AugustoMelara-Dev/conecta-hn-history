<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Inertia\Inertia;

class WelcomeController extends Controller
{
    public function index()
    {
        // Get featured PRO tenants for landing page
        $featuredTenants = Tenant::where('status', 'approved')
            ->where('is_pro', true)
            ->select('id', 'name', 'slug', 'logo_path', 'blur_hash', 'city_id', 'description', 'category_id')
            ->with('city:id,name', 'category:id,name')
            ->inRandomOrder()
            ->take(6)
            ->get();

        return Inertia::render('Welcome', [
            'featuredTenants' => $featuredTenants,
        ]);
    }
}
