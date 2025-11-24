<?php

namespace App\Observers;

use App\Models\Product;

class ProductObserver
{
    /**
     * Handle the Product "creating" event.
     *
     * SECURITY DIRECTIVE 3: Data Synchronization
     * Auto-assign city_id from tenant to prevent invisible products
     */
    public function creating(Product $product): void
    {
        // If city_id is not manually set, inherit from tenant
        if (empty($product->city_id) && $product->tenant_id) {
            $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($product->tenant_id);

            if ($tenant && $tenant->city_id) {
                $product->city_id = $tenant->city_id;
            }
        }
    }

    /**
     * Handle the Product "saved" event.
     */
    public function saved(Product $product): void
    {
        // Pilar 7: Auto-generate Blurhash when image changes
        if ($product->isDirty('image_path') && ! empty($product->image_path)) {
            $fullPath = storage_path('app/public/'.$product->image_path);

            if (file_exists($fullPath)) {
                try {
                    // Generate blurhash using kornrunner/blurhash
                    $image = imagecreatefromstring(file_get_contents($fullPath));
                    if ($image !== false) {
                        $width = imagesx($image);
                        $height = imagesy($image);

                        $hash = \kornrunner\Blurhash\Blurhash::encode($image, 4, 3);
                        $product->updateQuietly(['blur_hash' => $hash]);

                        imagedestroy($image);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Blurhash generation failed for product {$product->id}: ".$e->getMessage());
                }
            }
        }
    }
}
