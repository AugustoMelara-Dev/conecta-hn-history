<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Tenant;
use App\Notifications\OrderStatusChangedNotification;

function makeStatusOrder(array $overrides = []): Order
{
    $defaults = [
        'id' => 200,
        'order_number' => '#2001',
        'total' => 199.99,
        'tenant_id' => '00000000-0000-0000-0000-000000000001',
        'customer_name' => 'Carlos Reyes',
        'customer_email' => 'carlos@example.com',
        'cancel_reason' => null,
        'tenant_name' => 'Restaurante El Patio',
    ];

    $data = array_merge($defaults, $overrides);

    $order = new Order;
    $order->forceFill([
        'id' => $data['id'],
        'order_number' => $data['order_number'],
        'total' => $data['total'],
        'tenant_id' => $data['tenant_id'],
        'customer_name' => $data['customer_name'],
        'customer_email' => $data['customer_email'],
        'cancel_reason' => $data['cancel_reason'],
    ]);

    $tenant = new Tenant;
    $tenant->forceFill(['name' => $data['tenant_name']]);
    $order->setRelation('tenant', $tenant);

    return $order;
}

test('confirmed status uses spanish label and emoji in subject', function () {
    $notification = new OrderStatusChangedNotification(makeStatusOrder(), 'pending', 'confirmed');

    $mail = $notification->toMail((object) []);

    expect($mail->subject)->toContain('Confirmado');
    expect($mail->subject)->toContain('#2001');
});

test('processing body remains localized', function () {
    $notification = new OrderStatusChangedNotification(makeStatusOrder(), 'confirmed', 'processing');

    $mail = $notification->toMail((object) []);
    $body = collect($mail->introLines)->implode(' ');

    expect($body)->toContain('preparado');
    expect($body)->toContain('L. 199.99');
});

test('greeting includes customer name', function () {
    $notification = new OrderStatusChangedNotification(makeStatusOrder(), 'pending', 'confirmed');

    expect($notification->toMail((object) [])->greeting)->toContain('Carlos Reyes');
});

test('cancelled notification includes cancel reason when present', function () {
    $notification = new OrderStatusChangedNotification(
        makeStatusOrder(['cancel_reason' => 'Sin stock suficiente']),
        'pending',
        'cancelled'
    );

    $body = collect($notification->toMail((object) [])->introLines)->implode(' ');

    expect($body)->toContain('Sin stock suficiente');
});

test('shouldSend returns false when customer email is empty', function () {
    $notification = new OrderStatusChangedNotification(
        makeStatusOrder(['customer_email' => '']),
        'pending',
        'confirmed'
    );

    expect($notification->shouldSend((object) [], 'mail'))->toBeFalse();
});

test('shouldSend returns true when customer email exists', function () {
    $notification = new OrderStatusChangedNotification(
        makeStatusOrder(['customer_email' => 'test@example.com']),
        'pending',
        'confirmed'
    );

    expect($notification->shouldSend((object) [], 'mail'))->toBeTrue();
});

test('via returns only mail channel', function () {
    $notification = new OrderStatusChangedNotification(makeStatusOrder(), 'pending', 'confirmed');

    expect($notification->via((object) []))->toBe(['mail']);
});

test('toArray returns required keys with spanish status label', function () {
    $notification = new OrderStatusChangedNotification(makeStatusOrder(), 'pending', 'delivered');

    $array = $notification->toArray((object) []);

    expect($array)->toHaveKeys([
        'order_id',
        'order_number',
        'previous_status',
        'new_status',
        'status_label',
    ]);
    expect($array['status_label'])->toBe('Entregado');
    expect($array['previous_status'])->toBe('pending');
    expect($array['new_status'])->toBe('delivered');
});

test('unknown status falls back to ucfirst in mail and array payload', function () {
    $notification = new OrderStatusChangedNotification(makeStatusOrder(), 'pending', 'refunded');

    $array = $notification->toArray((object) []);
    $mail = $notification->toMail((object) []);

    expect($array['status_label'])->toBe('Refunded');
    expect($mail->subject)->toContain('Actualización');
});
