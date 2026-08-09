<?php

declare(strict_types=1);

use Carbon\Carbon;

use function Pest\Laravel\get;

describe('Localization Configuration', function () {
    it('sets carbon locale to spanish during http request', function () {
        // Make an HTTP request to trigger full Laravel bootstrap
        get('/');

        // Carbon::getLocale() should return the global locale
        expect(Carbon::getLocale())->toBe('es');
    });

    it('renders dates in spanish with diffForHumans', function () {
        // Trigger full bootstrap
        get('/');

        // Arrange
        $date = Carbon::now()->subMinute();

        // Act
        $diff = $date->diffForHumans();

        // Assert: Spanish output "hace X minuto(s)"
        expect($diff)->toContain('hace');
    });

    it('renders translated month names in spanish', function () {
        // Trigger full bootstrap
        get('/');

        // Arrange: A date in January
        $date = Carbon::parse('2026-01-15');

        // Act: Use translated format
        $formatted = $date->translatedFormat('F');

        // Assert: Spanish month name
        expect(strtolower($formatted))->toBe('enero');
    });
});
