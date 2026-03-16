<?php

declare(strict_types=1);

/**
 * VC-AUDIT-008/009/011 Compliance Tests
 *
 * Verifies security hardening fixes are in place.
 */

// ═══════════════════════════════════════════════════════════════════════════
// VC-AUDIT-009: ReportDownloadController urldecode fix
// ═══════════════════════════════════════════════════════════════════════════

test('report download controller uses urldecode not urnDecode', function () {
    $controllerPath = app_path('Http/Controllers/Api/V1/ReportDownloadController.php');
    $content = file_get_contents($controllerPath);

    expect($content)->not->toContain('urnDecode');
    expect($content)->toContain('urldecode');
});

test('report download validates path without fatal error', function () {
    $controller = app(\App\Http\Controllers\Api\V1\ReportDownloadController::class);
    $method = new ReflectionMethod($controller, 'validateAndSanitizePath');
    $method->setAccessible(true);

    // Test URL-encoded path - should not throw "undefined function" error
    $result = $method->invoke($controller, 'reports%2Ftest.pdf');

    // Key assertion: no Fatal Error, returns sanitized path
    expect($result)->toBe('reports/test.pdf');
});

test('report download blocks path traversal', function () {
    $controller = app(\App\Http\Controllers\Api\V1\ReportDownloadController::class);
    $method = new ReflectionMethod($controller, 'validateAndSanitizePath');
    $method->setAccessible(true);

    expect($method->invoke($controller, 'reports/../../../etc/passwd'))->toBeNull();
    expect($method->invoke($controller, 'reports/..\\..\\config.php'))->toBeNull();
});

// ═══════════════════════════════════════════════════════════════════════════
// VC-AUDIT-008: ProductResource FileUpload validation
// ═══════════════════════════════════════════════════════════════════════════

test('product resource has file type and size validation', function () {
    $resourcePath = app_path('Filament/App/Resources/ProductResource.php');
    $content = file_get_contents($resourcePath);

    expect($content)->toContain('acceptedFileTypes');
    expect($content)->toContain('maxSize');
});

// ═══════════════════════════════════════════════════════════════════════════
// VC-AUDIT-011: PaymentResource ANSI SQL
// ═══════════════════════════════════════════════════════════════════════════

test('payment resource uses ANSI SQL not MySQL FIELD', function () {
    $resourcePath = app_path('Filament/Resources/PaymentResource.php');
    $content = file_get_contents($resourcePath);

    expect($content)->not->toContain('FIELD(status');
    expect($content)->toContain('CASE status');
});
