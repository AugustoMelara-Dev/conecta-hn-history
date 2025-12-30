<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantPasswordRequest;
use App\Http\Requests\Tenant\UpdateTenantProfileRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SettingsController - Unified Settings Hub
 *
 * ARCHITECTURE: Request Segregation
 * - Profile updates: UpdateTenantProfileRequest → Tenant model
 * - Password changes: UpdateTenantPasswordRequest → User model
 */
final class SettingsController extends Controller
{
    /**
     * Day labels for schedule matrix (Spanish).
     */
    private const DAY_LABELS = [
        'monday' => 'Lunes',
        'tuesday' => 'Martes',
        'wednesday' => 'Miércoles',
        'thursday' => 'Jueves',
        'friday' => 'Viernes',
        'saturday' => 'Sábado',
        'sunday' => 'Domingo',
    ];

    /**
     * Default schedule structure.
     */
    private const DEFAULT_SCHEDULE = [
        'monday' => ['isOpen' => true, 'open' => '08:00', 'close' => '17:00'],
        'tuesday' => ['isOpen' => true, 'open' => '08:00', 'close' => '17:00'],
        'wednesday' => ['isOpen' => true, 'open' => '08:00', 'close' => '17:00'],
        'thursday' => ['isOpen' => true, 'open' => '08:00', 'close' => '17:00'],
        'friday' => ['isOpen' => true, 'open' => '08:00', 'close' => '17:00'],
        'saturday' => ['isOpen' => true, 'open' => '09:00', 'close' => '14:00'],
        'sunday' => ['isOpen' => false, 'open' => null, 'close' => null],
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // INDEX - Settings Hub Page
    // ═══════════════════════════════════════════════════════════════════════
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant($request);

        return Inertia::render('Tenant/Settings/Index', [
            'tenant' => $this->transformTenant($tenant),
            'schedule' => $tenant->schedule ?? self::DEFAULT_SCHEDULE,
            'dayLabels' => self::DAY_LABELS,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // UPDATE PROFILE - Tenant Model Update
    // ═══════════════════════════════════════════════════════════════════════
    public function updateProfile(UpdateTenantProfileRequest $request): RedirectResponse
    {
        $tenant = $this->getTenant($request);
        $validated = $request->validated();

        // Handle logo upload
        if ($request->hasFile('logo')) {
            if ($tenant->logo_path) {
                Storage::disk('public')->delete($tenant->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store("tenants/{$tenant->id}", 'public');
        }

        // Handle cover upload
        if ($request->hasFile('cover')) {
            if ($tenant->cover_path) {
                Storage::disk('public')->delete($tenant->cover_path);
            }
            $validated['cover_path'] = $request->file('cover')->store("tenants/{$tenant->id}", 'public');
        }

        $tenant->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? $tenant->description,
            'about' => $validated['about'] ?? $tenant->about,
            'phone' => $validated['phone'] ?? $tenant->phone,
            'whatsapp_number' => $validated['whatsapp_number'] ?? $tenant->whatsapp_number,
            'address' => $validated['address'] ?? $tenant->address,
            'instagram_url' => $validated['instagram_url'] ?? $tenant->instagram_url,
            'facebook_url' => $validated['facebook_url'] ?? $tenant->facebook_url,
            'tiktok_url' => $validated['tiktok_url'] ?? $tenant->tiktok_url,
            'website_url' => $validated['website_url'] ?? $tenant->website_url,
            'logo_path' => $validated['logo_path'] ?? $tenant->logo_path,
            'cover_path' => $validated['cover_path'] ?? $tenant->cover_path,
        ]);

        return back()->with('success', 'Perfil actualizado exitosamente.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // UPDATE SCHEDULE - Business Hours Update
    // ═══════════════════════════════════════════════════════════════════════
    public function updateSchedule(Request $request): RedirectResponse
    {
        $tenant = $this->getTenant($request);

        $validated = $request->validate([
            'schedule' => ['required', 'array'],
            'schedule.*.isOpen' => ['required', 'boolean'],
            'schedule.*.open' => ['nullable', 'date_format:H:i'],
            'schedule.*.close' => ['nullable', 'date_format:H:i'],
        ]);

        $tenant->update(['schedule' => $validated['schedule']]);

        return back()->with('success', 'Horarios actualizados.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get tenant from authenticated user.
     */
    private function getTenant(Request $request): Tenant
    {
        $tenant = $request->user()?->tenant;
        abort_unless($tenant, 403, 'Tenant not found.');
        return $tenant;
    }

    /**
     * Transform tenant for frontend.
     */
    private function transformTenant(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'description' => $tenant->description,
            'about' => $tenant->about,
            'phone' => $tenant->phone,
            'whatsapp_number' => $tenant->whatsapp_number,
            'address' => $tenant->address,
            'instagram_url' => $tenant->instagram_url,
            'facebook_url' => $tenant->facebook_url,
            'tiktok_url' => $tenant->tiktok_url,
            'website_url' => $tenant->website_url,
            'logo_url' => $tenant->logo_path ? Storage::url($tenant->logo_path) : null,
            'cover_url' => $tenant->cover_path ? Storage::url($tenant->cover_path) : null,
            'is_pro' => (bool) $tenant->is_pro,
        ];
    }
}
