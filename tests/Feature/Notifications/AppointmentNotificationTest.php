<?php

declare(strict_types=1);

use App\Events\Appointment\AppointmentCreated;
use App\Listeners\SendAppointmentConfirmation;
use App\Mail\AppointmentConfirmationMail;
use App\Models\Appointment;
use Illuminate\Contracts\Queue\ShouldQueue;

describe('Notification Classes Exist', function () {
    it('AppointmentCreated event class exists', function () {
        expect(class_exists(AppointmentCreated::class))->toBeTrue();
    });

    it('AppointmentConfirmationMail class exists', function () {
        expect(class_exists(AppointmentConfirmationMail::class))->toBeTrue();
    });

    it('SendAppointmentConfirmation listener class exists', function () {
        expect(class_exists(SendAppointmentConfirmation::class))->toBeTrue();
    });
});

describe('Event Configuration', function () {
    it('AppointmentCreated event uses Dispatchable trait', function () {
        $traits = class_uses_recursive(AppointmentCreated::class);
        expect($traits)->toHaveKey('Illuminate\Foundation\Events\Dispatchable');
    });

    it('Appointment model dispatches created event', function () {
        $reflection = new ReflectionClass(Appointment::class);
        $property = $reflection->getProperty('dispatchesEvents');
        $property->setAccessible(true);

        $events = $property->getValue(new Appointment);

        expect($events)->toHaveKey('created');
        expect($events['created'])->toBe(AppointmentCreated::class);
    });
});

describe('Listener Configuration', function () {
    it('SendAppointmentConfirmation implements ShouldQueue (async)', function () {
        $listener = new SendAppointmentConfirmation;
        expect($listener)->toBeInstanceOf(ShouldQueue::class);
    });

    it('SendAppointmentConfirmation has handle method', function () {
        expect((new ReflectionClass(SendAppointmentConfirmation::class))->hasMethod('handle'))->toBeTrue();
    });

    it('handle method accepts AppointmentCreated event', function () {
        $method = new ReflectionMethod(SendAppointmentConfirmation::class, 'handle');
        $params = $method->getParameters();
        $type = $params[0]->getType();

        expect($params)->toHaveCount(1);

        if (! $type instanceof ReflectionNamedType) {
            throw new RuntimeException('Listener handle parameter type must be named.');
        }

        expect($type->getName())->toBe(AppointmentCreated::class);
    });
});

describe('Mailable Configuration', function () {
    it('AppointmentConfirmationMail extends Mailable', function () {
        $reflection = new ReflectionClass(AppointmentConfirmationMail::class);
        expect($reflection->isSubclassOf('Illuminate\Mail\Mailable'))->toBeTrue();
    });

    it('AppointmentConfirmationMail has envelope method', function () {
        expect((new ReflectionClass(AppointmentConfirmationMail::class))->hasMethod('envelope'))->toBeTrue();
    });

    it('AppointmentConfirmationMail has content method', function () {
        expect((new ReflectionClass(AppointmentConfirmationMail::class))->hasMethod('content'))->toBeTrue();
    });
});

describe('Email Template', function () {
    it('confirmation template file exists', function () {
        $path = resource_path('views/emails/appointment/confirmation.blade.php');
        expect(file_exists($path))->toBeTrue();
    });

    it('template contains appointment confirmation content', function () {
        $path = resource_path('views/emails/appointment/confirmation.blade.php');
        $content = file_get_contents($path);

        expect($content)->toContain('cita está confirmada');
        expect($content)->toContain('customer_name');
        expect($content)->toContain('tenantName');
    });
});
