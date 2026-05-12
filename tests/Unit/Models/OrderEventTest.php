<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\OrderEvent;
use Tests\TestCase;

uses(TestCase::class);

/**
 * VC-AUDIT-005 Verification: OrderEvent Mass Assignment Protection
 *
 * This test verifies that critical audit fields (id, occurred_at)
 * cannot be mass-assigned, preserving audit log integrity.
 */
test('order_event guards id against mass assignment', function () {
    $orderEvent = new OrderEvent();
    $guarded = $orderEvent->getGuarded();

    expect($guarded)->toContain('id');
});

test('order_event guards occurred_at against mass assignment', function () {
    $orderEvent = new OrderEvent();
    $guarded = $orderEvent->getGuarded();

    expect($guarded)->toContain('occurred_at');
});

test('order_event does not have empty guarded array', function () {
    $orderEvent = new OrderEvent();
    $guarded = $orderEvent->getGuarded();

    expect($guarded)->not->toBeEmpty();
});
