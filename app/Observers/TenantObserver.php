<?php

namespace App\Observers;

use App\Models\Tenant;

class TenantObserver
{
    /**
     * Handle the Tenant "created" event.
     */
    public function created(Tenant $tenant): void
    {
        // Note: Categories are global and assigned via category_id
        // Tenants belong to ONE category (Restaurantes, Tecnología, etc.)
        // They don't create their own categories

        // 1. Asignar Rol de Dueño de Negocio
        $user = $tenant->user;
        if ($user && $user->role !== 'seller') {
            $user->update(['role' => 'seller']);
        }
    }

    /**
     * Handle the Tenant "updated" event.
     */
    public function updated(Tenant $tenant): void
    {
        //
    }

    /**
     * Handle the Tenant "saved" event.
     */
    public function saved(Tenant $tenant): void
    {
        // Pilar 7: Auto-generate Blurhash when logo changes
        if ($tenant->isDirty('logo_path') && ! empty($tenant->logo_path)) {
            $fullPath = storage_path('app/public/'.$tenant->logo_path);

            if (file_exists($fullPath)) {
                try {
                    // Generate blurhash using kornrunner/blurhash
                    $image = imagecreatefromstring(file_get_contents($fullPath));
                    if ($image !== false) {
                        $width = imagesx($image);
                        $height = imagesy($image);

                        $hash = \kornrunner\Blurhash\Blurhash::encode($image, 4, 3);
                        $tenant->updateQuietly(['blur_hash' => $hash]);

                        imagedestroy($image);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Blurhash generation failed for tenant {$tenant->id}: ".$e->getMessage());
                }
            }
        }
    }

    /**
     * Handle the Tenant "deleted" event.
     */
    public function deleted(Tenant $tenant): void
    {
        //
    }

    /**
     * Handle the Tenant "restored" event.
     */
    public function restored(Tenant $tenant): void
    {
        //
    }

    /**
     * Handle the Tenant "force deleted" event.
     */
    public function forceDeleted(Tenant $tenant): void
    {
        //
    }
}
