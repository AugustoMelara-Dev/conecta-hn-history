<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTenantSettingsRequest;
use Illuminate\Http\JsonResponse;

/**
 * TenantSettingsController - Secure Self-Service Configuration
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  IDOR PREVENTION: Tenant ID never accepted from client                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * SECURITY MODEL:
 * All operations use $request->user()->tenant as the SOLE source of tenant context.
 * A malicious user CANNOT update another tenant's settings by manipulating IDs.
 *
 * USAGE:
 * PATCH /api/settings
 * Authorization: Bearer {token}
 * Content-Type: application/json
 * {
 *   "bank_name": "BAC Credomatic",
 *   "bank_account": "123456789",
 *   "bank_account_name": "Mi Negocio S.A."
 * }
 */
class TenantSettingsController extends Controller
{
    /**
     * Update tenant settings (banking info, WhatsApp, logo, etc.)
     *
     * PATCH /api/settings
     *
     * SUPPORTS:
     * - JSON fields (bank_name, bank_account, whatsapp_number)
     * - File uploads (logo) via multipart/form-data
     *
     * SECURITY:
     * - auth:sanctum middleware enforces authentication
     * - UpdateTenantSettingsRequest validates input
     * - Tenant is derived from authenticated user (no ID in request)
     */
    public function update(UpdateTenantSettingsRequest $request): JsonResponse
    {
        // ═══════════════════════════════════════════════════════════════════════
        // SECURE TENANT RESOLUTION (CurrentUser Pattern)
        // ═══════════════════════════════════════════════════════════════════════
        // We NEVER accept tenant_id from the request body.
        // The tenant is determined exclusively from the authenticated user.
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene un negocio asociado a esta cuenta.',
            ], 403);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PIPELINE STEP 1: EXPLICIT LOGO REMOVAL (Nullification Signal)
        // ═══════════════════════════════════════════════════════════════════════
        // Process remove_logo FIRST to clear the slate.
        // This operation is INDEPENDENT of the upload step below.
        if ($request->boolean('remove_logo') && $tenant->logo_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($tenant->logo_path);
            $tenant->logo_path = null;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PIPELINE STEP 2: LOGO UPLOAD (Independent Operation)
        // ═══════════════════════════════════════════════════════════════════════
        // This is a SEPARATE if block (NOT elseif) to support "Replace" operations
        // where both removal and upload occur in sequence.
        //
        // SEQUENTIAL BEHAVIOR:
        // - "Remove Only": Step 1 runs, Step 2 skipped → logo_path = null
        // - "Upload Only": Step 1 skipped, Step 2 runs → logo_path = new file
        // - "Replace":     Step 1 clears old, Step 2 uploads new → logo_path = new file
        if ($request->hasFile('logo')) {
            // Garbage collection: delete any existing file (may still exist if Step 1 didn't run)
            if ($tenant->logo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($tenant->logo_path);
            }

            // Store new logo with unique filename
            $path = $request->file('logo')->store('logos', 'public');
            $tenant->logo_path = $path;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PARTIAL UPDATE (Only provided fields are updated)
        // ═══════════════════════════════════════════════════════════════════════
        $validated = $request->validated();

        // Remove handled keys from validated
        unset($validated['logo'], $validated['remove_logo']);

        // Fill only the provided fields
        $tenant->fill($validated);
        $tenant->save();

        return response()->json([
            'success' => true,
            'message' => 'Configuración actualizada exitosamente.',
            'data' => [
                'bank_name' => $tenant->bank_name,
                'bank_account' => $tenant->bank_account,
                'bank_account_name' => $tenant->bank_account_name,
                'bank_account_type' => $tenant->bank_account_type,
                'whatsapp_number' => $tenant->whatsapp_number,
                // Return resolved URL for instant UI update
                'logo_url' => $tenant->logo_path
                    ? \Illuminate\Support\Facades\Storage::url($tenant->logo_path)
                    : null,
            ],
        ]);
    }

    /**
     * Get current tenant settings.
     *
     * GET /api/settings
     */
    public function show(): JsonResponse
    {
        $tenant = auth()->user()?->tenant;

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene un negocio asociado a esta cuenta.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'bank_name' => $tenant->bank_name,
                'bank_account' => $tenant->bank_account,
                'bank_account_name' => $tenant->bank_account_name,
                'bank_account_type' => $tenant->bank_account_type,
                'whatsapp' => $tenant->whatsapp,
                'whatsapp_number' => $tenant->whatsapp_number,
            ],
        ]);
    }
}
