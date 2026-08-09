<?php

declare(strict_types=1);

use App\Models\Order;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

// Bootstrap Laravel app — needed because toMail() calls loadMissing()
uses(TestCase::class);

// ═══════════════════════════════════════════════════════════════════════════
// HELPER: Build a fully-mocked Order for status-change notification testing
// ═══════════════════════════════════════════════════════════════════════════

function makeStatusOrder(array $overrides = []): Order
{
    $defaults = [
        'id' => 200,
        'order_number' => '#2001',
        'total' => 199.99,
        'tenant_id' => 7,
        'customer_name' => 'Carlos Reyes',
        'customer_email' => 'carlos@example.com',
        'cancel_reason' => null,
        'formatted_total' => 'L. 199.99',
    ];

    $data = array_merge($defaults, $overrides);
    $data['formatted_total'] = 'L. '.number_format((float) $data['total'], 2);

    // Build tenant
    if (($data['_null_tenant'] ?? false) === false) {
        $tenant = new stdClass;
        $tenant->name = $data['tenant_name'] ?? 'Restaurante El Patio';
        $data['tenant'] = $tenant;
    } else {
        $data['tenant'] = null;
    }

    $order = Mockery::mock(Order::class);
    $order->shouldReceive('loadMissing')->andReturnSelf();
    $order->shouldReceive('getAttribute')->andReturnUsing(fn ($key) => $data[$key] ?? null);
    $order->shouldReceive('__get')->andReturnUsing(fn ($key) => $data[$key] ?? null);
    $order->shouldReceive('offsetExists')->andReturnUsing(fn ($key) => array_key_exists($key, $data));

    return $order;
}

// ═══════════════════════════════════════════════════════════════════════════
// LOCALIZATION: Status labels are in Spanish, never raw English
// ═══════════════════════════════════════════════════════════════════════════

test('confirmed status shows Spanish label and emoji in subject', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'pending', 'confirmed');

    $mail = $notification->toMail((object) []);

    expect($mail->subject)->toContain('Confirmado');
    expect($mail->subject)->toContain('#2001');
    expect($mail->subject)->toContain('✅');
    expect($mail->subject)->not->toContain('confirmed');
});

test('processing status shows En Preparación in subject', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'confirmed', 'processing');

    $mail = $notification->toMail((object) []);

    expect($mail->subject)->toContain('Preparación');
    expect($mail->subject)->toContain('👨‍🍳');
    expect($mail->subject)->not->toContain('processing');
});

test('ready status shows Listo in subject', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'processing', 'ready');

    $mail = $notification->toMail((object) []);

    expect($mail->subject)->toContain('Listo');
    expect($mail->subject)->toContain('🎉');
});

test('delivered status shows Entregado in subject', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'ready', 'delivered');

    $mail = $notification->toMail((object) []);

    expect($mail->subject)->toContain('Entregado');
    expect($mail->subject)->toContain('📦');
});

test('cancelled status shows Cancelado in subject', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'pending', 'cancelled');

    $mail = $notification->toMail((object) []);

    expect($mail->subject)->toContain('Cancelado');
    expect($mail->subject)->toContain('❌');
});

// ═══════════════════════════════════════════════════════════════════════════
// LOCALIZATION: Body content is always Spanish
// ═══════════════════════════════════════════════════════════════════════════

test('processing body contains Spanish preparation message', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'confirmed', 'processing');

    $mail = $notification->toMail((object) []);

    $body = collect($mail->introLines)->implode(' ');
    expect($body)->toContain('preparado');
    expect($body)->not->toContain('is being processed');
});

test('delivered body contains gratitude message in Spanish', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'ready', 'delivered');

    $mail = $notification->toMail((object) []);

    $body = collect($mail->introLines)->implode(' ');
    expect($body)->toContain('entregado');
    expect($body)->toContain('preferencia');
});

test('body includes formatted total', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'pending', 'confirmed');

    $mail = $notification->toMail((object) []);

    $body = collect($mail->introLines)->implode(' ');
    expect($body)->toContain('L. 199.99');
});

test('body includes order number', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'pending', 'confirmed');

    $mail = $notification->toMail((object) []);

    $body = collect($mail->introLines)->implode(' ');
    expect($body)->toContain('#2001');
});

// ═══════════════════════════════════════════════════════════════════════════
// GREETING: Uses customer name
// ═══════════════════════════════════════════════════════════════════════════

test('greeting uses customer name', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'pending', 'confirmed');

    $mail = $notification->toMail((object) []);

    expect($mail->greeting)->toContain('Carlos Reyes');
});

// ═══════════════════════════════════════════════════════════════════════════
// BRANDING: Tenant name appears in subject and salutation
// ═══════════════════════════════════════════════════════════════════════════

test('subject includes tenant name', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'pending', 'confirmed');

    $mail = $notification->toMail((object) []);

    expect($mail->subject)->toContain('Restaurante El Patio');
});

test('null tenant falls back to Tu Negocio', function () {
    $order = makeStatusOrder(['_null_tenant' => true]);
    $notification = new OrderStatusChangedNotification($order, 'pending', 'confirmed');

    $mail = $notification->toMail((object) []);

    expect($mail->subject)->toContain('Tu Negocio');
});

test('salutation includes tenant name', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'pending', 'confirmed');

    $mail = $notification->toMail((object) []);

    expect($mail->salutation)->toContain('Restaurante El Patio');
});

// ═══════════════════════════════════════════════════════════════════════════
// CANCELLATION: Includes reason when available
// ═══════════════════════════════════════════════════════════════════════════

test('cancelled notification includes cancel reason when present', function () {
    $order = makeStatusOrder(['cancel_reason' => 'Sin stock suficiente']);
    $notification = new OrderStatusChangedNotification($order, 'pending', 'cancelled');

    $mail = $notification->toMail((object) []);

    $body = collect($mail->introLines)->implode(' ');
    expect($body)->toContain('Sin stock suficiente');
});

test('cancelled notification omits reason line when null', function () {
    $order = makeStatusOrder(['cancel_reason' => null]);
    $notification = new OrderStatusChangedNotification($order, 'pending', 'cancelled');

    $mail = $notification->toMail((object) []);

    $body = collect($mail->introLines)->implode(' ');
    expect($body)->not->toContain('Motivo');
});

// ═══════════════════════════════════════════════════════════════════════════
// DEFENSIVE: shouldSend() guard
// ═══════════════════════════════════════════════════════════════════════════

test('shouldSend returns false when customer_email is empty', function () {
    $order = makeStatusOrder(['customer_email' => '']);
    $notification = new OrderStatusChangedNotification($order, 'pending', 'confirmed');

    Log::shouldReceive('info')->once()->withArgs(function ($msg) {
        return str_contains($msg, 'skipped');
    });

    expect($notification->shouldSend((object) [], 'mail'))->toBeFalse();
});

test('shouldSend returns false when customer_email is null', function () {
    $order = makeStatusOrder(['customer_email' => null]);
    $notification = new OrderStatusChangedNotification($order, 'pending', 'confirmed');

    Log::shouldReceive('info')->once();

    expect($notification->shouldSend((object) [], 'mail'))->toBeFalse();
});

test('shouldSend returns true when customer_email exists', function () {
    $order = makeStatusOrder(['customer_email' => 'test@example.com']);
    $notification = new OrderStatusChangedNotification($order, 'pending', 'confirmed');

    expect($notification->shouldSend((object) [], 'mail'))->toBeTrue();
});

// ═══════════════════════════════════════════════════════════════════════════
// via: Only mail channel (customer-facing)
// ═══════════════════════════════════════════════════════════════════════════

test('via returns only mail channel', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'pending', 'confirmed');

    expect($notification->via((object) []))->toBe(['mail']);
});

// ═══════════════════════════════════════════════════════════════════════════
// toArray: Database structure
// ═══════════════════════════════════════════════════════════════════════════

test('toArray returns all required keys', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'pending', 'confirmed');

    $array = $notification->toArray((object) []);

    expect($array)->toHaveKeys([
        'order_id',
        'order_number',
        'previous_status',
        'new_status',
        'status_label',
    ]);
});

test('toArray status_label is Spanish not English', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'pending', 'delivered');

    $array = $notification->toArray((object) []);

    expect($array['status_label'])->toBe('Entregado');
    expect($array['previous_status'])->toBe('pending');
    expect($array['new_status'])->toBe('delivered');
});

// ═══════════════════════════════════════════════════════════════════════════
// UNKNOWN STATUS: Graceful fallback
// ═══════════════════════════════════════════════════════════════════════════

test('unknown status falls back to ucfirst in toArray', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'pending', 'refunded');

    $array = $notification->toArray((object) []);

    expect($array['status_label'])->toBe('Refunded');
});

test('unknown status uses default subject with Actualización', function () {
    $order = makeStatusOrder();
    $notification = new OrderStatusChangedNotification($order, 'pending', 'refunded');

    $mail = $notification->toMail((object) []);

    expect($mail->subject)->toContain('Actualización');
    expect($mail->subject)->toContain('#2001');
});
