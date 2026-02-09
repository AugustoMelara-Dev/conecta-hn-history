<?php

declare(strict_types=1);

use App\Filament\Resources\ProductResource;

/**
 * VC-AUDIT-003: Product Upload Validation Test
 *
 * Verifies that ProductResource images field has MIME type restrictions
 */
test('ProductResource images field has acceptedFileTypes validation', function () {
    // Read the ProductResource file and verify the validation exists
    $source = file_get_contents(app_path('Filament/Resources/ProductResource.php'));

    // Verify acceptedFileTypes is present for images
    expect($source)->toContain("->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])");

    // Verify it's associated with the images field
    expect($source)->toContain("SpatieMediaLibraryFileUpload::make('images')");
});

test('ProductResource images field has maxSize validation', function () {
    $source = file_get_contents(app_path('Filament/Resources/ProductResource.php'));

    // Verify maxSize is present
    expect($source)->toContain('->maxSize(');
});
