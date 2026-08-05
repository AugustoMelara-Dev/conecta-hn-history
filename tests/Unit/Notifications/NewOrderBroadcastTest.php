<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Tests\TestCase;

uses(TestCase::class);

const NEW_ORDER_TENANT_ID = '00000000-0000-4000-8000-000000000042';

/**
 * NewOrderNotification Broadcasting Tests
 *
 * Verifies that NewOrderNotification implements real-time broadcasting
 * via the ShouldBroadcast interface for WebSocket delivery.
 */
describe('NewOrderNotification Broadcasting', function () {
    it('implements ShouldBroadcast interface', function () {
        $reflection = new ReflectionClass(NewOrderNotification::class);

        expect($reflection->implementsInterface(ShouldBroadcast::class))->toBeTrue();
    });

    it('includes broadcast in via channels', function () {
        // Create mock order
        $order = new Order;
        $order->id = 1;
        $order->tenant_id = NEW_ORDER_TENANT_ID;
        $order->order_number = 'TEST-001';
        $order->total = 100.00;
        $order->customer_name = 'Test Customer';

        $notification = new NewOrderNotification($order);
        $user = new User;

        $channels = $notification->via($user);

        expect($channels)
            ->toContain('database')
            ->toContain('mail')
            ->toContain('broadcast');
    });

    it('broadcasts on tenant admin private channel', function () {
        $order = new Order;
        $order->id = 1;
        $order->tenant_id = NEW_ORDER_TENANT_ID;
        $order->order_number = 'TEST-001';

        $notification = new NewOrderNotification($order);
        $channels = $notification->broadcastOn();

        expect($channels)->toHaveCount(1);
        expect($channels[0])->toBeInstanceOf(PrivateChannel::class);

        // Verify channel name follows pattern tenant.{id}.admins
        $channelName = $channels[0]->name;
        expect($channelName)->toBe('private-tenant.'.NEW_ORDER_TENANT_ID.'.admins');
    });

    it('has lightweight broadcast payload', function () {
        $order = new Order;
        $order->id = 123;
        $order->tenant_id = NEW_ORDER_TENANT_ID;
        $order->order_number = 'ORD-456';
        $order->total = 250.50;
        $order->customer_name = 'Juan Perez';

        $notification = new NewOrderNotification($order);
        $user = new User;

        $broadcastMessage = $notification->toBroadcast($user);
        $data = $broadcastMessage->data;

        expect($data)
            ->toHaveKey('order_id', 123)
            ->toHaveKey('order_number', 'ORD-456')
            ->toHaveKey('amount', 250.50)
            ->toHaveKey('tenant_id', NEW_ORDER_TENANT_ID)
            ->toHaveKey('customer_name', 'Juan Perez');
    });
});
