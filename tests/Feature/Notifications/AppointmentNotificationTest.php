<?php

declare(strict_types=1);

use App\Domain\Contracts\AdminNotifier;
use App\Domain\TenantGovernance\Services\OperationalPlaybookTaskManager;
use App\Events\Appointment\AppointmentCreated;
use App\Listeners\NotifyAppointmentBookedListener;
use App\Listeners\SendAppointmentConfirmation;
use App\Mail\AppointmentConfirmationMail;
use App\Models\Appointment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Config;

afterEach(function () {
    Mockery::close();
});

function makeAppointmentCreatedEvent(): AppointmentCreated
{
    $appointment = new Appointment;
    $appointment->forceFill([
        'id' => 1,
        'tenant_id' => '00000000-0000-0000-0000-000000000001',
    ]);

    return new AppointmentCreated($appointment);
}

function makeAdminNotifier(): AdminNotifier
{
    return new class implements AdminNotifier
    {
        public function notifyNewLead(object $lead): void {}

        public function notifyNewReview(object $review): void {}

        public function notifyNewOrder(int|string $tenantId, int $orderId, float $total): void {}

        public function notifyPaymentUpdate(int|string $tenantId, string $status, float $amount): void {}

        public function notifyAppointmentBooked(object $appointment): void {}

        public function notifyAppointmentReminder(object $appointment): void {}

        public function notifyRecoveryOfferReady(object $tenant, ?string $customerName, string $couponCode, float $subtotal): void {}

        public function notifyRepeatPurchaseOpportunity(object $order): void {}

        public function notifyRenewalAttention(object $tenant, int $daysUntilExpiry): void {}
    };
}

function makeOperationalPlaybookTaskManager(): OperationalPlaybookTaskManager
{
    return (new ReflectionClass(OperationalPlaybookTaskManager::class))->newInstanceWithoutConstructor();
}

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

    it('appointment listeners do not queue during browser release e2e', function () {
        Config::set('app.e2e_browser_release', true);
        $event = makeAppointmentCreatedEvent();

        $notificationListener = new NotifyAppointmentBookedListener(
            makeAdminNotifier(),
            makeOperationalPlaybookTaskManager(),
        );

        expect((new SendAppointmentConfirmation)->shouldQueue($event))->toBeFalse();
        expect($notificationListener->shouldQueue($event))->toBeFalse();
    });

    it('appointment listeners still queue outside browser release e2e', function () {
        Config::set('app.e2e_browser_release', false);
        $event = makeAppointmentCreatedEvent();

        $notificationListener = new NotifyAppointmentBookedListener(
            makeAdminNotifier(),
            makeOperationalPlaybookTaskManager(),
        );

        expect((new SendAppointmentConfirmation)->shouldQueue($event))->toBeTrue();
        expect($notificationListener->shouldQueue($event))->toBeTrue();
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
