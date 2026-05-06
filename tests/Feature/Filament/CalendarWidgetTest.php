<?php

declare(strict_types=1);

use App\Filament\App\Widgets\AppointmentCalendarWidget;
use App\Models\Appointment;
use Livewire\Livewire;

// ═══════════════════════════════════════════════════════════════════════
// CALENDAR WIDGET TESTS - Filament FullCalendar Integration
// ═══════════════════════════════════════════════════════════════════════

describe('AppointmentCalendarWidget', function () {
    it('widget class exists', function () {
        expect(class_exists(AppointmentCalendarWidget::class))->toBeTrue();
    });

    it('extends FullCalendarWidget', function () {
        $reflection = new ReflectionClass(AppointmentCalendarWidget::class);
        
        expect($reflection->getParentClass()->getName())
            ->toBe('Saade\FilamentFullCalendar\Widgets\FullCalendarWidget');
    });

    it('has fetchEvents method', function () {
        expect(method_exists(AppointmentCalendarWidget::class, 'fetchEvents'))->toBeTrue();
    });

    it('has config method', function () {
        expect(method_exists(AppointmentCalendarWidget::class, 'config'))->toBeTrue();
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
