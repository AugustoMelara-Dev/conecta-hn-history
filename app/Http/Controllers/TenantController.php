<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TenantController extends Controller
{
    public function show(Tenant $tenant)
    {
        // Load relationships with blur_hash for progressive loading
        $tenant->load(['city', 'category', 'reviews.user', 'products' => function ($q) {
            $q->select('id', 'tenant_id', 'name', 'slug', 'image_path', 'blur_hash', 'price', 'description')
                ->where('is_visible', true)
                ->where('is_locked', false)
                ->take(12);
        }]);

        // Generate SEO metadata for server-side injection (HYBRID SEO)
        $meta = [
            'title' => $tenant->name.' - Conecta HN',
            'description' => Str::limit($tenant->description ?? 'Descubre productos y servicios de calidad en Honduras', 155),
            'image' => $tenant->logo_path ? Storage::url($tenant->logo_path) : asset('img/default-og.jpg'),
            'url' => route('tenant.show', $tenant->slug),
        ];

        // Check opening hours
        $isOpen = false;
        if ($tenant->hours_data) {
            try {
                $openingHours = $tenant->hours_data;
                $isOpen = $openingHours->isOpenAt(now());
            } catch (\Exception $e) {
                \Log::error('Opening hours check failed', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);
            }
        }

        // Check if user is following
        $isFollowing = false;
        if (\Illuminate\Support\Facades\Auth::check()) {
            $isFollowing = \Illuminate\Support\Facades\Auth::user()
                ->following()
                ->where('tenant_id', $tenant->id)
                ->exists();
        }

        return Inertia::render('Tenant/Show', [
            'tenant' => $tenant,
            'isOpen' => $isOpen,
            'openingHours' => $tenant->hours_data, // Pass this so frontend knows if hours exist
            'isFollowing' => $isFollowing,
        ])->withViewData(['meta' => $meta]); // SEO injection for crawlers
    }
}
