<?php

declare(strict_types=1);

/**
 * BudgetEnforcer Feature Tests
 *
 * Estos tests verifican la lógica del BudgetEnforcer con BD real (SQLite en memoria).
 * Están en Feature/ porque requieren RefreshDatabase y el ciclo de vida completo de Laravel.
 */

use App\Exceptions\InsufficientAiCreditsException;
use App\Infrastructure\AI\BudgetEnforcer;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function createBudgetTenant(): Tenant
{
    return Tenant::factory()->create([
        'ai_balance' => 1.000000,
        'ai_reserved_balance' => 0.000000,
    ]);
}

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: reserve() METHOD
// ═══════════════════════════════════════════════════════════════════════════

test('reserve increments ai_reserved_balance when sufficient funds', function () {
    $tenant = createBudgetTenant();
    $budgetEnforcer = new BudgetEnforcer;

    $reservationId = $budgetEnforcer->reserve(
        tenantId: (string) $tenant->id,
        estimatedCost: 0.50,
        operation: 'test_operation'
    );

    expect($reservationId)->toBeString()->toHaveLength(32);
    expect(DB::table('ai_credit_reservations')->where('id', $reservationId)->value('status'))->toBe('pending');

    $tenant->refresh();
    expect((float) $tenant->ai_reserved_balance)->toBe(0.50);
    expect((float) $tenant->ai_balance)->toBe(1.00); // Balance no cambia
});

test('reserve throws InsufficientAiCreditsException when balance too low', function () {
    $tenant = createBudgetTenant();
    $budgetEnforcer = new BudgetEnforcer;

    expect(fn () => $budgetEnforcer->reserve(
        tenantId: (string) $tenant->id,
        estimatedCost: 5.00,
        operation: 'expensive_operation'
    ))->toThrow(InsufficientAiCreditsException::class);

    $tenant->refresh();
    expect((float) $tenant->ai_reserved_balance)->toBe(0.00);
});

test('reserve considers already reserved balance', function () {
    $tenant = createBudgetTenant();
    $budgetEnforcer = new BudgetEnforcer;

    $budgetEnforcer->reserve((string) $tenant->id, 0.60, 'first');

    expect(fn () => $budgetEnforcer->reserve(
        tenantId: (string) $tenant->id,
        estimatedCost: 0.50,
        operation: 'second'
    ))->toThrow(InsufficientAiCreditsException::class);
});

test('reserve throws RuntimeException for non-existent tenant', function () {
    $budgetEnforcer = new BudgetEnforcer;

    expect(fn () => $budgetEnforcer->reserve(
        tenantId: '00000000-0000-4000-8000-999999999999',
        estimatedCost: 0.10,
        operation: 'test'
    ))->toThrow(RuntimeException::class, 'Tenant 00000000-0000-4000-8000-999999999999 no encontrado');
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: settle() METHOD
// ═══════════════════════════════════════════════════════════════════════════

test('settle decrements both balances correctly', function () {
    $tenant = createBudgetTenant();
    $budgetEnforcer = new BudgetEnforcer;

    $reservationId = $budgetEnforcer->reserve((string) $tenant->id, 0.50, 'test');

    $budgetEnforcer->settle(
        tenantId: (string) $tenant->id,
        estimatedCost: 0.50,
        actualCost: 0.30,
        reservationId: $reservationId,
        usageMetadata: ['model' => 'gpt-4o']
    );

    $tenant->refresh();

    expect((float) $tenant->ai_reserved_balance)->toBe(0.00);
    expect((float) $tenant->ai_balance)->toBe(0.70);
    expect(DB::table('ai_credit_reservations')->where('id', $reservationId)->value('status'))->toBe('settled');
});

test('settle is idempotent and cannot charge twice', function () {
    $tenant = createBudgetTenant();
    $budgetEnforcer = new BudgetEnforcer;
    $reservationId = $budgetEnforcer->reserve((string) $tenant->id, 0.50, 'test');

    $budgetEnforcer->settle((string) $tenant->id, 0.50, 0.30, $reservationId);
    $budgetEnforcer->settle((string) $tenant->id, 0.50, 0.30, $reservationId);

    expect((float) $tenant->refresh()->ai_balance)->toBe(0.70);
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: refund() METHOD
// ═══════════════════════════════════════════════════════════════════════════

test('refund only decrements ai_reserved_balance', function () {
    $tenant = createBudgetTenant();
    $budgetEnforcer = new BudgetEnforcer;

    $reservationId = $budgetEnforcer->reserve((string) $tenant->id, 0.50, 'test');

    $budgetEnforcer->refund(
        tenantId: (string) $tenant->id,
        estimatedCost: 0.50,
        reservationId: $reservationId,
        failureReason: 'API timeout'
    );

    $tenant->refresh();

    expect((float) $tenant->ai_reserved_balance)->toBe(0.00);
    expect((float) $tenant->ai_balance)->toBe(1.00);
    expect(DB::table('ai_credit_reservations')->where('id', $reservationId)->value('status'))->toBe('refunded');
});

test('refund is idempotent and cannot release another reservation', function () {
    $tenant = createBudgetTenant();
    $budgetEnforcer = new BudgetEnforcer;
    $reservationId = $budgetEnforcer->reserve((string) $tenant->id, 0.50, 'test');

    $budgetEnforcer->refund((string) $tenant->id, 0.50, $reservationId, 'timeout');
    $budgetEnforcer->refund((string) $tenant->id, 0.50, $reservationId, 'retry');

    expect((float) $tenant->refresh()->ai_reserved_balance)->toBe(0.00);
});

test('stale pending reservations are reconciled by the scheduled command', function () {
    $tenant = createBudgetTenant();
    $reservationId = (new BudgetEnforcer)->reserve((string) $tenant->id, 0.50, 'test');
    DB::table('ai_credit_reservations')->where('id', $reservationId)->update([
        'created_at' => now()->subHours(2),
    ]);

    expect(Artisan::call('ai:reconcile-reservations', ['--older-than' => 60]))->toBe(0);
    expect(DB::table('ai_credit_reservations')->where('id', $reservationId)->value('status'))->toBe('refunded');
    expect((float) $tenant->refresh()->ai_reserved_balance)->toBe(0.00);
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: getAvailableBalance() METHOD
// ═══════════════════════════════════════════════════════════════════════════

test('getAvailableBalance returns balance minus reserved', function () {
    $tenant = createBudgetTenant();
    $budgetEnforcer = new BudgetEnforcer;

    $budgetEnforcer->reserve((string) $tenant->id, 0.30, 'test');

    $available = $budgetEnforcer->getAvailableBalance((string) $tenant->id);

    expect($available)->toBe(0.70);
});

test('getAvailableBalance returns zero for non-existent tenant', function () {
    $available = (new BudgetEnforcer)->getAvailableBalance('00000000-0000-4000-8000-999999999999');

    expect($available)->toBe(0.0);
});
