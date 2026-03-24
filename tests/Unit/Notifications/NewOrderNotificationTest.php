<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Tenant;
use App\Notifications\NewOrderNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;

function makeNotificationOrder(array $overrides = []): Order
{
    $defaults = [
        'id' => 100,
        'order_number' => '#1042',
        'total' => 350.50,
        'tenant_id' => '00000000-0000-0000-0000-000000000001',
        'customer_name' => 'María López',
        'customer_email' => 'maria@example.com',
        'created_at' => now(),
        'tenant_name' => 'Café Tegus',
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
        'created_at' => $data['created_at'],
    ]);
    $order->setRelation('items', new Collection);

    $tenant = new Tenant;
    $tenant->forceFill(['name' => $data['tenant_name']]);
    $order->setRelation('tenant', $tenant);

    return $order;
}

test('toMail includes order number and tenant in subject', function () {
    $notification = new NewOrderNotification(makeNotificationOrder());

    $mail = $notification->toMail((object) []);

    expect($mail)->toBeInstanceOf(MailMessage::class);
    expect($mail->subject)->toContain('#1042');
    expect($mail->subject)->toContain('Café Tegus');
});

test('toMail includes customer name and formatted total in body', function () {
    $notification = new NewOrderNotification(makeNotificationOrder());

    $mail = $notification->toMail((object) []);
    $body = collect($mail->introLines)->implode(' ');

    expect($body)->toContain('María López');
    expect($body)->toContain('L. 350.50');
});

test('toMail falls back to generic customer name when missing', function () {
    $notification = new NewOrderNotification(makeNotificationOrder([
        'customer_name' => null,
    ]));

    $mail = $notification->toMail((object) []);
    $body = collect($mail->introLines)->implode(' ');

    expect($body)->toContain('Cliente');
});

test('toMail includes order action button', function () {
    $notification = new NewOrderNotification(makeNotificationOrder());

    $mail = $notification->toMail((object) []);

    expect($mail->actionText)->toBe('Ver Pedido');
    expect($mail->actionUrl)->toContain('/app/orders/100');
});

test('toArray returns required database payload', function () {
    $notification = new NewOrderNotification(makeNotificationOrder());

    $array = $notification->toArray((object) []);

    expect($array)->toHaveKeys([
        'order_id',
        'order_number',
        'formatted_total',
        'customer_name',
        'items_count',
        'action_url',
        'message',
    ]);
    expect($array['order_id'])->toBe(100);
    expect($array['formatted_total'])->toBe('L. 350.50');
});

test('via returns all expected channels', function () {
    $notification = new NewOrderNotification(makeNotificationOrder());

    expect($notification->via((object) []))->toContain('database')
        ->toContain('mail')
        ->toContain('broadcast')
        ->toContain(\NotificationChannels\WebPush\WebPushChannel::class);
});

test('toBroadcast includes order and tenant identifiers', function () {
    $notification = new NewOrderNotification(makeNotificationOrder());

    $broadcast = $notification->toBroadcast((object) []);

    expect($broadcast->data['order_id'])->toBe(100);
    expect($broadcast->data['tenant_id'])->toBe('00000000-0000-0000-0000-000000000001');
    expect($broadcast->data['customer_name'])->toBe('María López');
});

test('broadcastOn uses tenant-scoped private channel', function () {
    $notification = new NewOrderNotification(makeNotificationOrder());

    $channels = $notification->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe('private-tenant.00000000-0000-0000-0000-000000000001.admins');
});
