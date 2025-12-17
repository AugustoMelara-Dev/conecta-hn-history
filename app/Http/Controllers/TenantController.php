<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TenantController extends Controller
{
    /**
     * Display the specified tenant microsite.
     * MANUAL LOOKUP IMPLEMENTATION to avoid implicit binding issues.
     */
    public function show(string $slug)
    {
        // 1. Manual Query for Robustness
        $tenant = Tenant::where('slug', $slug)
            ->with([
                'city',
                'category',
                'products' => function ($query) {
                    $query->where('is_visible', true)
                        ->orderBy('sort_order', 'asc') // Asumiendo que existe sort_order
                        ->orderBy('created_at', 'desc');
                },
            ])
            ->first();

        // 2. Handle 404 Case (Tenant does not exist)
        if (! $tenant) {
            abort(404, 'Tienda no encontrada.');
        }

        // 3. Security & Visibility Gate
        // Allow access only if:
        // - Status is 'approved' AND is_active is true
        // - OR Current User is the Owner
        // - OR Current User is an Admin

        $canAccess = false;

        if ($tenant->status === 'approved' && $tenant->is_active) {
            $canAccess = true;
        } elseif (Auth::check()) {
            $user = Auth::user();
            if ($user->id === $tenant->user_id || in_array($user->role, ['admin', 'super_admin'])) {
                $canAccess = true;
            }
        }

        if (! $canAccess) {
            // Return 404 to hide pending/inactive stores from public
            abort(404, 'Esta tienda no está disponible públicamente aún.');
        }

        // 4. Record Visit (Optional logic if you have it)
        // visits($tenant)->increment();

        // 5. Logic for PRO vs FREE Differentiation
        // Filter sensitive or premium data based on plan
        if (! $tenant->is_pro) {
            // Free Plan Limitations applied at View level or here
            // e.g., limiting product count in the collection passed to view if not already limited
            // For now, we pass all but frontend handles UI differences
        }

        // 6. Check if Open
        $isOpen = $this->checkIfOpen($tenant);

        return Inertia::render('Tenant/Show', [
            'tenant' => $tenant,
            'products' => $tenant->products,
            'categories' => [], // If you had product categories
            'isOpen' => $isOpen,
            'meta' => [
                'title' => $tenant->name,
                'description' => $tenant->description,
                'image' => $tenant->banner_path ? Storage::url($tenant->banner_path) : null,
            ],
        ]);
    }

    /**
     * Helper to check opening hours
     */
    protected function checkIfOpen(Tenant $tenant): bool
    {
        if (! $tenant->hours_data) {
            return false;
        }

        // Simple logic placeholder.
        // Real implementation depends on your OpeningHours package/cast
        try {
            if (is_callable([$tenant->hours_data, 'isOpenAt'])) {
                return $tenant->hours_data->isOpenAt(now());
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }
}
