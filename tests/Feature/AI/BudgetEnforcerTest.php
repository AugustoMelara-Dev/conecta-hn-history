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

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crear tenant de prueba con saldo inicial
    $this->tenant = Tenant::factory()->create([
        'ai_balance' => 1.000000,
        'ai_reserved_balance' => 0.000000,
    ]);
    
    $this->budgetEnforcer = new BudgetEnforcer();
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: reserve() METHOD
// ═══════════════════════════════════════════════════════════════════════════

test('reserve increments ai_reserved_balance when sufficient funds', function () {
    $reservationId = $this->budgetEnforcer->reserve(
        tenantId: $this->tenant->id,
        estimatedCost: 0.50,
        operation: 'test_operation'
    );

    expect($reservationId)->toBeString()->toHaveLength(16);

    $this->tenant->refresh();
    expect((float) $this->tenant->ai_reserved_balance)->toBe(0.50);
    expect((float) $this->tenant->ai_balance)->toBe(1.00); // Balance no cambia
});

test('reserve throws InsufficientAiCreditsException when balance too low', function () {
    expect(fn() => $this->budgetEnforcer->reserve(
        tenantId: $this->tenant->id,
        estimatedCost: 5.00,
        operation: 'expensive_operation'
    ))->toThrow(InsufficientAiCreditsException::class);

    $this->tenant->refresh();
    expect((float) $this->tenant->ai_reserved_balance)->toBe(0.00);
});

test('reserve considers already reserved balance', function () {
    $this->budgetEnforcer->reserve($this->tenant->id, 0.60, 'first');
    
    expect(fn() => $this->budgetEnforcer->reserve(
        tenantId: $this->tenant->id,
        estimatedCost: 0.50,
        operation: 'second'
    ))->toThrow(InsufficientAiCreditsException::class);
});

test('reserve throws RuntimeException for non-existent tenant', function () {
    expect(fn() => $this->budgetEnforcer->reserve(
        tenantId: 999999,
        estimatedCost: 0.10,
        operation: 'test'
    ))->toThrow(RuntimeException::class, 'Tenant 999999 no encontrado');
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: settle() METHOD
// ═══════════════════════════════════════════════════════════════════════════

test('settle decrements both balances correctly', function () {
    $reservationId = $this->budgetEnforcer->reserve($this->tenant->id, 0.50, 'test');
    
    $this->budgetEnforcer->settle(
        tenantId: $this->tenant->id,
        estimatedCost: 0.50,
        actualCost: 0.30,
        reservationId: $reservationId,
        usageMetadata: ['model' => 'gpt-4o']
    );

    $this->tenant->refresh();
    
    expect((float) $this->tenant->ai_reserved_balance)->toBe(0.00);
    expect((float) $this->tenant->ai_balance)->toBe(0.70);
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: refund() METHOD
// ═══════════════════════════════════════════════════════════════════════════

test('refund only decrements ai_reserved_balance', function () {
    $reservationId = $this->budgetEnforcer->reserve($this->tenant->id, 0.50, 'test');
    
    $this->budgetEnforcer->refund(
        tenantId: $this->tenant->id,
        estimatedCost: 0.50,
        reservationId: $reservationId,
        failureReason: 'API timeout'
    );

    $this->tenant->refresh();
    
    expect((float) $this->tenant->ai_reserved_balance)->toBe(0.00);
    expect((float) $this->tenant->ai_balance)->toBe(1.00);
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: getAvailableBalance() METHOD
// ═══════════════════════════════════════════════════════════════════════════

test('getAvailableBalance returns balance minus reserved', function () {
    $this->budgetEnforcer->reserve($this->tenant->id, 0.30, 'test');
    
    $available = $this->budgetEnforcer->getAvailableBalance($this->tenant->id);
    
    expect($available)->toBe(0.70);
});

test('getAvailableBalance returns zero for non-existent tenant', function () {
    $available = $this->budgetEnforcer->getAvailableBalance(999999);
    
    expect($available)->toBe(0.0);
});
