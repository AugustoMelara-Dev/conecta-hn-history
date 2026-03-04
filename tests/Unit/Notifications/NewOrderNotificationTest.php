<?php



declare(strict_types=1);

use App\Notifications\NewOrderNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;

// Bootstrap Laravel app — needed because NewOrderNotification::toMail() calls url()
// uses(Tests\TestCase::class);


// ═══════════════════════════════════════════════════════════════════════════
// HELPER: Build a test double that satisfies the Order type hint
// but never touches the database.
//
// STRATEGY: We use Mockery::mock(Order::class) without makePartial()
// so Model::boot() and BelongsToTenant are never invoked.
// Every property access goes through __get → getAttribute, so we
// mock getAttribute to return the correct values.
// ═══════════════════════════════════════════════════════════════════════════

function makeNotificationOrder(array $overrides = []): \App\Models\Order
{
    $defaults = [
        'id' => 100,
        'order_number' => '#1042',
        'total' => 350.50,
        'tenant_id' => '00000000-0000-0000-0000-000000000001',
        'customer_name' => 'María López',
        'customer_email' => 'maria@example.com',
        'formatted_total' => 'L. 350.50',
        'created_at' => null,
    ];

    $data = array_merge($defaults, $overrides);
    $data['formatted_total'] = 'L. ' . number_format((float) $data['total'], 2);
    $data['created_at'] = now();
    $data['items'] = new Collection();

    // Build tenant
    if (($overrides['_null_tenant'] ?? false) === false) {
        $tenant = new \stdClass();
        $tenant->name = $overrides['tenant_name'] ?? 'Café Tegus';
        $data['tenant'] = $tenant;
    } else {
        $data['tenant'] = null;
    }

    $order = Mockery::mock(\App\Models\Order::class);

    // loadMissing (called in toMail + toArray) → no-op
    $order->shouldReceive('loadMissing')->andReturnSelf();

    // Property access: Eloquent models proxy __get to getAttribute
    $order->shouldReceive('getAttribute')->andReturnUsing(function ($key) use ($data) {
        return $data[$key] ?? null;
    });

    // Direct property access via __get
    $order->shouldReceive('__get')->andReturnUsing(function ($key) use ($data) {
        return $data[$key] ?? null;
    });

    // offsetExists for isset($order->prop)
    $order->shouldReceive('offsetExists')->andReturnUsing(function ($key) use ($data) {
        return array_key_exists($key, $data);
    });

    return $order;
}

// ═══════════════════════════════════════════════════════════════════════════
// toMail: Subject & Structure
// ═══════════════════════════════════════════════════════════════════════════

test('toMail includes order number in subject', function () {
    $order = makeNotificationOrder();
    $notification = new NewOrderNotification($order);

    $mail = $notification->toMail((object) []);

    expect($mail)->toBeInstanceOf(MailMessage::class);
    expect($mail->subject)->toContain('#1042');
});

test('toMail includes tenant name in subject', function () {
    $order = makeNotificationOrder();
    $notification = new NewOrderNotification($order);

    $mail = $notification->toMail((object) []);

    expect($mail->subject)->toContain('Café Tegus');
});

test('toMail includes customer name in body', function () {
    $order = makeNotificationOrder();
    $notification = new NewOrderNotification($order);

    $mail = $notification->toMail((object) []);

    $body = collect($mail->introLines)->implode(' ');
    expect($body)->toContain('María López');
});

test('toMail includes formatted total in body', function () {
    $order = makeNotificationOrder();
    $notification = new NewOrderNotification($order);

    $mail = $notification->toMail((object) []);

    $body = collect($mail->introLines)->implode(' ');
    expect($body)->toContain('L. 350.50');
});

test('toMail includes View Order action button', function () {
    $order = makeNotificationOrder();
    $notification = new NewOrderNotification($order);

    $mail = $notification->toMail((object) []);

    expect($mail->actionText)->toBe('Ver Pedido');
    expect($mail->actionUrl)->toContain('/admin/orders/100/edit');
});

// ═══════════════════════════════════════════════════════════════════════════
// toMail: Graceful fallbacks
// ═══════════════════════════════════════════════════════════════════════════

test('toMail handles null customer name with fallback', function () {
    $order = makeNotificationOrder(['customer_name' => null]);
    $notification = new NewOrderNotification($order);

    $mail = $notification->toMail((object) []);

    $body = collect($mail->introLines)->implode(' ');
    expect($body)->toContain('Cliente');
});

test('toMail handles null tenant with fallback', function () {
    $order = makeNotificationOrder(['_null_tenant' => true]);
    $notification = new NewOrderNotification($order);

    $mail = $notification->toMail((object) []);

    expect($mail->subject)->toContain('Tu Negocio');
});

// ═══════════════════════════════════════════════════════════════════════════
// toArray: Database storage structure
// ═══════════════════════════════════════════════════════════════════════════

test('toArray returns all required database keys', function () {
    $order = makeNotificationOrder();
    $notification = new NewOrderNotification($order);

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
});

test('toArray contains correct values', function () {
    $order = makeNotificationOrder();
    $notification = new NewOrderNotification($order);

    $array = $notification->toArray((object) []);

    expect($array['order_id'])->toBe(100);
    expect($array['order_number'])->toBe('#1042');
    expect($array['formatted_total'])->toBe('L. 350.50');
    expect($array['action_url'])->toBe('/admin/orders/100/edit');
});

// ═══════════════════════════════════════════════════════════════════════════
// via: Delivery channels
// ═══════════════════════════════════════════════════════════════════════════

test('via returns database mail and broadcast channels', function () {
    $order = makeNotificationOrder();
    $notification = new NewOrderNotification($order);

    $channels = $notification->via((object) []);

    expect($channels)->toBe(['database', 'mail', 'broadcast']);
});

// ═══════════════════════════════════════════════════════════════════════════
// toBroadcast: WebSocket payload
// ═══════════════════════════════════════════════════════════════════════════

test('toBroadcast includes order_id and tenant_id', function () {
    $order = makeNotificationOrder();
    $notification = new NewOrderNotification($order);

    $broadcast = $notification->toBroadcast((object) []);

    expect($broadcast->data['order_id'])->toBe(100);
    expect($broadcast->data['tenant_id'])->toBe(5);
    expect($broadcast->data['customer_name'])->toBe('María López');
});

// ═══════════════════════════════════════════════════════════════════════════
// broadcastOn: Tenant-scoped channel
// ═══════════════════════════════════════════════════════════════════════════

test('broadcasts on tenant-scoped private channel', function () {
    $order = makeNotificationOrder();
    $notification = new NewOrderNotification($order);

    $channels = $notification->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe('private-tenant.5.admins');
});
