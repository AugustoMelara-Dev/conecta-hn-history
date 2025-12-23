<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * WelcomeController - Landing Page with Data Transformation Layer
 *
 * Responsabilidad: Hidratar el componente Welcome.jsx con datos transformados
 * que coincidan exactamente con el contrato de interfaz del frontend.
 */
class WelcomeController extends Controller
{
    /**
     * Display the landing page with fully transformed data.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Welcome', [
            // Auth is handled by HandleInertiaRequests middleware

            // Metrics: Real-time platform stats
            'metrics' => [
                'total_tenants' => Tenant::where('is_active', true)->count(),
                'total_products' => \App\Models\Product::where('is_visible', true)->count(),
            ],

            // Categories: Transform to ensure all fields exist
            'categories' => Category::query()
                ->where('is_active', true)
                ->select(['id', 'name', 'slug', 'icon'])
                ->orderBy('name')
                ->limit(8)
                ->get()
                ->map(fn (Category $cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'icon' => $cat->icon ?? '🏪', // Fallback icon
                ]),

            // Featured Tenants: Full transformation with Storage URLs
            'featuredTenants' => $this->getFeaturedTenants(),
        ]);
    }

    /**
     * Get featured tenants with complete data transformation.
     *
     * QUALITY FILTERS (Strict):
     * 1. plan_type = 'pro' or 'enterprise' (monetization)
     * 2. subscription_status = 'active' (no morosos)
     * 3. hero_image IS NOT NULL (visual quality)
     */
    private function getFeaturedTenants(): array
    {
        return Tenant::query()
            ->where('is_active', true)
            // Monetization filter: Only premium tenants
            ->whereIn('plan_type', ['pro', 'enterprise'])
            // Status filter: Only active subscriptions
            ->where('subscription_status', 'active')
            // Quality filter: Must have hero image
            ->whereNotNull('hero_image')
            ->with(['city:id,name', 'category:id,name,slug'])
            ->inRandomOrder()
            ->limit(6)
            ->get()
            ->map(function (Tenant $tenant) {
                return [
                    // Core fields
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'description' => $tenant->description ?? 'El mejor servicio de la zona.',

                    // Images: Use hero_image as primary cover
                    'cover' => $this->resolveImageUrl($tenant->hero_image ?? $tenant->cover_path, 'covers'),
                    'logo' => $this->resolveImageUrl($tenant->logo_path, 'logos'),
                    'blur_hash' => $tenant->blur_hash,

                    // Flattened relations
                    'city' => $tenant->city?->name ?? 'Honduras',
                    'category' => $tenant->category ? [
                        'id' => $tenant->category->id,
                        'name' => $tenant->category->name,
                        'slug' => $tenant->category->slug,
                    ] : ['id' => 0, 'name' => 'General', 'slug' => 'general'],

                    // Computed is_pro from accessor
                    'is_pro' => $tenant->is_pro,

                    // Mocked fields
                    'rating' => 5.0,
                ];
            })
            ->toArray();
    }

    /**
     * Resolve image path to full URL with placeholder fallback.
     */
    private function resolveImageUrl(?string $path, string $type): string
    {
        if (empty($path)) {
            // Return placeholder based on type
            return match ($type) {
                'covers' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=800&h=600&fit=crop',
                'logos' => 'https://ui-avatars.com/api/?name=ConectaHN&background=0ea5e9&color=fff&size=200',
                default => 'https://via.placeholder.com/400',
            };
        }

        // If it's already a full URL, return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Use Storage URL for local files
        return Storage::url($path);
    }
}
