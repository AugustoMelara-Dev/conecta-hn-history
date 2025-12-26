<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * HandleInertiaRequests - Global State Hydration Middleware
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GLOBAL DATA PROVIDER: Injects context into every React page render   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * SHARED DATA ARCHITECTURE:
 * Instead of the Frontend making /api/user and /api/settings calls on every
 * page load, this middleware "pushes" the necessary context automatically.
 *
 * INJECTED PROPS:
 * - auth.user: Current authenticated user (sanitized, no password hash)
 * - tenant: Active tenant context (branding, bank details for checkout)
 * - flash: Session flash messages (success/error for toast notifications)
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * These props are merged into every Inertia page response, eliminating
     * the need for redundant API calls from React components.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            // ═══════════════════════════════════════════════════════════════════════
            // AUTH CONTEXT
            // ═══════════════════════════════════════════════════════════════════════
            // Provides authenticated user data to all pages.
            // SECURITY: Only expose safe fields (no password, tokens, etc.)
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
            ],

            // ═══════════════════════════════════════════════════════════════════════
            // TENANT CONTEXT
            // ═══════════════════════════════════════════════════════════════════════
            // Provides active tenant for branding and checkout instructions.
            // Eliminates need for Frontend to query /api/settings on every page.
            'tenant' => fn () => $this->getTenantData($request),

            // ═══════════════════════════════════════════════════════════════════════
            // FLASH MESSAGES
            // ═══════════════════════════════════════════════════════════════════════
            // Enables toast notifications from Backend redirects.
            // Usage: return redirect()->back()->with('success', 'Product saved!');
            // React accesses: usePage().props.flash.success
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }

    /**
     * Get sanitized tenant data for the Frontend.
     *
     * SECURITY: Only expose necessary fields for UI rendering.
     * Sensitive operational data stays server-side.
     *
     * @return array|null
     */
    protected function getTenantData(Request $request): ?array
    {
        // Try to get tenant from authenticated user
        $tenant = $request->user()?->tenant;

        // Fallback: Try to get from route parameter or app container
        if (! $tenant && app()->has('currentTenant')) {
            $tenant = app('currentTenant');
        }

        if (! $tenant) {
            return null;
        }

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'logo_url' => $tenant->logo_path ? \Illuminate\Support\Facades\Storage::url($tenant->logo_path) : null,
            // Contact info for header/footer
            'whatsapp' => $tenant->whatsapp,
            'whatsapp_number' => $tenant->whatsapp_number,
            'phone' => $tenant->phone,
            // Banking info for checkout instructions
            'bank_name' => $tenant->bank_name,
            'bank_account' => $tenant->bank_account,
            'bank_account_name' => $tenant->bank_account_name,
            'bank_account_type' => $tenant->bank_account_type,
            // Business settings
            'is_pro' => $tenant->is_pro ?? false,
        ];
    }
}

