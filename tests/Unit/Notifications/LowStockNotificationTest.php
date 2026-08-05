<?php

declare(strict_types=1);

use App\Enums\LowStockSeverity;
use App\Events\Broadcast\LowStockBroadcast;

// Bootstrap Laravel — needed for trans_choice() in buildMessage()
uses(Tests\TestCase::class);

// ═══════════════════════════════════════════════════════════════════════════
// HELPER: Build a LowStockBroadcast with sensible defaults
// ═══════════════════════════════════════════════════════════════════════════

function makeLowStockBroadcast(array $overrides = []): LowStockBroadcast
{
    $defaults = [
        'tenantId' => '00000000-0000-4000-8000-000000000005',
        'productId' => 42,
        'productName' => 'Tornillo Hexagonal',
        'productSku' => 'TH-001',
        'currentStock' => 2.0,
        'threshold' => 5.0,
        'severity' => LowStockSeverity::Warning,
        'occurredAt' => new DateTimeImmutable('2026-02-15T12:00:00+00:00'),
    ];

    $data = array_merge($defaults, $overrides);

    return new LowStockBroadcast(
        tenantId: $data['tenantId'],
        productId: $data['productId'],
        productName: $data['productName'],
        productSku: $data['productSku'],
        currentStock: $data['currentStock'],
        threshold: $data['threshold'],
        severity: $data['severity'],
        occurredAt: $data['occurredAt'],
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// BROADCAST PAYLOAD: Structure & Domain Data
// ═══════════════════════════════════════════════════════════════════════════

test('broadcastWith includes all required payload keys', function () {
    $broadcast = makeLowStockBroadcast();

    $payload = $broadcast->broadcastWith();

    expect($payload)->toHaveKeys([
        '_version',
        '_timestamp',
        'title',
        'message',
        'status',
        'icon',
        'tenant_id',
        'product_id',
        'product_name',
        'product_sku',
        'current_stock',
        'threshold',
        'severity',
        'severity_label',
        'severity_color',
        'occurred_at',
    ]);
});

test('broadcastWith contains correct domain values', function () {
    $broadcast = makeLowStockBroadcast();

    $payload = $broadcast->broadcastWith();

    expect($payload['tenant_id'])->toBe('00000000-0000-4000-8000-000000000005');
    expect($payload['product_id'])->toBe(42);
    expect($payload['product_name'])->toBe('Tornillo Hexagonal');
    expect($payload['product_sku'])->toBe('TH-001');
    expect($payload['current_stock'])->toBe(2.0);
    expect($payload['threshold'])->toBe(5.0);
    expect($payload['status'])->toBe('low_stock');
});

test('broadcastWith includes version number', function () {
    $broadcast = makeLowStockBroadcast();

    $payload = $broadcast->broadcastWith();

    expect($payload['_version'])->toBe(1);
});

test('broadcastWith includes ISO 8601 timestamp', function () {
    $broadcast = makeLowStockBroadcast();

    $payload = $broadcast->broadcastWith();

    expect($payload['_timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T/');
});

// ═══════════════════════════════════════════════════════════════════════════
// SEVERITY: Labels, Colors, Icons
// ═══════════════════════════════════════════════════════════════════════════

test('Warning severity shows correct label, color, and icon', function () {
    $broadcast = makeLowStockBroadcast(['severity' => LowStockSeverity::Warning]);

    $payload = $broadcast->broadcastWith();

    expect($payload['severity'])->toBe('warning');
    expect($payload['severity_label'])->toBe('Warning');
    expect($payload['severity_color'])->toBe('yellow');
    expect($payload['icon'])->toBe('alert-triangle');
});

test('Critical severity shows correct label, color, and icon', function () {
    $broadcast = makeLowStockBroadcast(['severity' => LowStockSeverity::Critical]);

    $payload = $broadcast->broadcastWith();

    expect($payload['severity'])->toBe('critical');
    expect($payload['severity_label'])->toBe('Critical');
    expect($payload['severity_color'])->toBe('red');
    expect($payload['icon'])->toBe('alert-octagon');
});

test('Info severity shows correct label, color, and icon', function () {
    $broadcast = makeLowStockBroadcast(['severity' => LowStockSeverity::Info]);

    $payload = $broadcast->broadcastWith();

    expect($payload['severity'])->toBe('info');
    expect($payload['severity_label'])->toBe('Informational');
    expect($payload['severity_color'])->toBe('blue');
    expect($payload['icon'])->toBe('info-circle');
});

// ═══════════════════════════════════════════════════════════════════════════
// SEVERITY ENUM: requiresImmediateAction
// ═══════════════════════════════════════════════════════════════════════════

test('Critical severity requires immediate action', function () {
    expect(LowStockSeverity::Critical->requiresImmediateAction())->toBeTrue();
});

test('Warning severity does NOT require immediate action', function () {
    expect(LowStockSeverity::Warning->requiresImmediateAction())->toBeFalse();
});

test('Info severity does NOT require immediate action', function () {
    expect(LowStockSeverity::Info->requiresImmediateAction())->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════
// CHANNEL SECURITY: Tenant-scoped admin broadcast
// ═══════════════════════════════════════════════════════════════════════════

test('broadcasts on tenant-scoped admin channel', function () {
    $broadcast = makeLowStockBroadcast(['tenantId' => '00000000-0000-4000-8000-000000000007']);

    $channels = $broadcast->broadcastOn();

    expect($channels)->toHaveCount(1);
    // ChannelRegistry::tenantAdmins() returns a private channel
    expect((string) $channels[0])->toContain('tenant.00000000-0000-4000-8000-000000000007.admins');
});

test('broadcastAs returns inventory.stock.low', function () {
    $broadcast = makeLowStockBroadcast();

    expect($broadcast->broadcastAs())->toBe('inventory.stock.low');
});

// ═══════════════════════════════════════════════════════════════════════════
// PAYLOAD: Message with product name and stock
// ═══════════════════════════════════════════════════════════════════════════

test('broadcast title uses severity label', function () {
    $broadcast = makeLowStockBroadcast(['severity' => LowStockSeverity::Warning]);

    $payload = $broadcast->broadcastWith();

    expect($payload['title'])->toBe('Warning');
});

test('broadcast title for Critical is Critical', function () {
    $broadcast = makeLowStockBroadcast(['severity' => LowStockSeverity::Critical]);

    $payload = $broadcast->broadcastWith();

    expect($payload['title'])->toBe('Critical');
});

// ═══════════════════════════════════════════════════════════════════════════
// FACTORY: fromDomainData static constructor
// ═══════════════════════════════════════════════════════════════════════════

test('fromDomainData creates correct instance', function () {
    $broadcast = LowStockBroadcast::fromDomainData(
        tenantId: '00000000-0000-4000-8000-000000000003',
        productId: 99,
        productName: 'Café Molido',
        productSku: 'CM-500',
        currentStock: 1.0,
        threshold: 10.0,
        severity: 'critical',
        occurredAt: new DateTimeImmutable('2026-01-01'),
    );

    $payload = $broadcast->broadcastWith();

    expect($payload['tenant_id'])->toBe('00000000-0000-4000-8000-000000000003');
    expect($payload['product_id'])->toBe(99);
    expect($payload['product_name'])->toBe('Café Molido');
    expect($payload['severity'])->toBe('critical');
    expect($payload['severity_label'])->toBe('Critical');
});

// ═══════════════════════════════════════════════════════════════════════════
// EDGE: Null SKU handling
// ═══════════════════════════════════════════════════════════════════════════

test('null SKU is passed through in payload', function () {
    $broadcast = makeLowStockBroadcast(['productSku' => null]);

    $payload = $broadcast->broadcastWith();

    expect($payload['product_sku'])->toBeNull();
});

// ═══════════════════════════════════════════════════════════════════════════
// SRE: Fail-fast configuration
// ═══════════════════════════════════════════════════════════════════════════

test('tries is set to 1 for fail-fast', function () {
    $broadcast = makeLowStockBroadcast();

    expect($broadcast->tries)->toBe(1);
});

test('timeout is set to 30 seconds', function () {
    $broadcast = makeLowStockBroadcast();

    expect($broadcast->timeout)->toBe(30);
});
