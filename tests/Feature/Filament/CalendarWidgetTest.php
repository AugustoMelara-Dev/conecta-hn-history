<?php

declare(strict_types=1);

use App\Filament\App\Widgets\AppointmentCalendarWidget;

// ═══════════════════════════════════════════════════════════════════════
// CALENDAR WIDGET TESTS - Filament FullCalendar Integration
// ═══════════════════════════════════════════════════════════════════════

describe('AppointmentCalendarWidget', function () {
    it('widget class exists', function () {
        expect(class_exists(AppointmentCalendarWidget::class))->toBeTrue();
    });

    it('uses the framework widget boundary instead of an incompatible calendar package', function () {
        $reflection = new ReflectionClass(AppointmentCalendarWidget::class);

        expect($reflection->getParentClass()->getName())
            ->toBe('Filament\Widgets\Widget');
        expect(file_get_contents(base_path('composer.json')))
            ->not->toContain('saade/filament-fullcalendar');
    });

    it('has fetchEvents method', function () {
        expect(method_exists(AppointmentCalendarWidget::class, 'fetchEvents'))->toBeTrue();
    });

    it('has config method', function () {
        expect(method_exists(AppointmentCalendarWidget::class, 'config'))->toBeTrue();
    });

    it('provides week navigation without third party runtime code', function () {
        expect(method_exists(AppointmentCalendarWidget::class, 'previousWeek'))->toBeTrue();
        expect(method_exists(AppointmentCalendarWidget::class, 'nextWeek'))->toBeTrue();
        expect(method_exists(AppointmentCalendarWidget::class, 'currentWeek'))->toBeTrue();
    });

    it('has status color mapping', function () {
        expect(method_exists(AppointmentCalendarWidget::class, 'getStatusColor'))->toBeTrue();
    });

    it('config returns valid calendar options', function () {
        $widget = new AppointmentCalendarWidget;
        $config = $widget->config();

        expect($config)->toBeArray();
        expect($config)->toHaveKey('initialView');
        expect($config['initialView'])->toBe('timeGridWeek');
        expect($config)->toHaveKey('headerToolbar');
        expect($config)->toHaveKey('slotMinTime');
        expect($config)->toHaveKey('slotMaxTime');
    });
});

describe('Appointment Status Colors', function () {
    it('pending status returns amber color', function () {
        $widget = new AppointmentCalendarWidget;
        $reflection = new ReflectionMethod($widget, 'getStatusColor');
        $reflection->setAccessible(true);

        expect($reflection->invoke($widget, 'pending'))->toBe('#f59e0b');
    });

    it('confirmed status returns blue color', function () {
        $widget = new AppointmentCalendarWidget;
        $reflection = new ReflectionMethod($widget, 'getStatusColor');
        $reflection->setAccessible(true);

        expect($reflection->invoke($widget, 'confirmed'))->toBe('#3b82f6');
    });

    it('completed status returns green color', function () {
        $widget = new AppointmentCalendarWidget;
        $reflection = new ReflectionMethod($widget, 'getStatusColor');
        $reflection->setAccessible(true);

        expect($reflection->invoke($widget, 'completed'))->toBe('#22c55e');
    });

    it('cancelled status returns red color', function () {
        $widget = new AppointmentCalendarWidget;
        $reflection = new ReflectionMethod($widget, 'getStatusColor');
        $reflection->setAccessible(true);

        expect($reflection->invoke($widget, 'cancelled'))->toBe('#ef4444');
    });
});
