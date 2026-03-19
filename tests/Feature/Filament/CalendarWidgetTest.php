<?php

declare(strict_types=1);

use App\Filament\App\Widgets\AppointmentCalendarWidget;
use Filament\Widgets\Widget;

describe('AppointmentCalendarWidget', function () {
    it('widget class exists', function () {
        expect(class_exists(AppointmentCalendarWidget::class))->toBeTrue();
    });

    it('extends the base Filament widget', function () {
        $reflection = new ReflectionClass(AppointmentCalendarWidget::class);
        $parent = $reflection->getParentClass();

        expect($parent)->not->toBeFalse();
        expect($parent instanceof ReflectionClass ? $parent->getName() : null)
            ->toBe(Widget::class);
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
