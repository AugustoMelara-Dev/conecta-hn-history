<?php

declare(strict_types=1);

/**
 * VC-AUDIT-010 FileUpload Validation Compliance Tests
 *
 * Verifies that all FileUpload components have acceptedFileTypes and maxSize.
 */

// ═══════════════════════════════════════════════════════════════════════════
// TenantResource FileUpload Validation
// ═══════════════════════════════════════════════════════════════════════════

test('tenant resource logo_path has security validation', function () {
    $resourcePath = app_path('Filament/Resources/TenantResource.php');
    $content = file_get_contents($resourcePath);

    // Find logo_path section
    expect($content)->toContain("::make('logo_path')");
    expect($content)->toContain("->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])");
    expect($content)->not->toContain("->acceptedFileTypes(['image/svg+xml'])");
});

test('tenant resource banner_path has security validation', function () {
    $resourcePath = app_path('Filament/Resources/TenantResource.php');
    $content = file_get_contents($resourcePath);

    // Find banner_path section
    expect($content)->toContain("::make('banner_path')");
    expect($content)->toContain("->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])");
});

// ═══════════════════════════════════════════════════════════════════════════
// PaymentResource FileUpload Validation
// ═══════════════════════════════════════════════════════════════════════════

test('payment resource proof_image_path has security validation', function () {
    $resourcePath = app_path('Filament/Resources/PaymentResource.php');
    $content = file_get_contents($resourcePath);

    expect($content)->toContain("::make('proof_image_path')");
    expect($content)->toContain("->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])");
    expect($content)->toContain('->maxSize(5120)');
});

// ═══════════════════════════════════════════════════════════════════════════
// CityResource FileUpload Validation
// ═══════════════════════════════════════════════════════════════════════════

test('city resource hero_image has security validation', function () {
    $resourcePath = app_path('Filament/Resources/CityResource.php');
    $content = file_get_contents($resourcePath);

    expect($content)->toContain("::make('hero_image')");
    expect($content)->toContain("->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])");
    expect($content)->toContain('->maxSize(4096)');
});
