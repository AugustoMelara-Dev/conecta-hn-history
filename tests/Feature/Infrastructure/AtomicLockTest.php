<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;

it('enforces exclusion when a lock is actively held', function () {
    $resourceKey = 'critical_transaction_REF_500';
    $store = new ArrayStore();

    $lockProcessA = $store->lock($resourceKey);
    $acquiredA = $lockProcessA->get();

    expect($acquiredA)->toBeTrue('Process A failed to acquire initial lock');

    $lockProcessB = $store->lock($resourceKey);
    $acquiredB = $lockProcessB->get();

    expect($acquiredB)->toBeFalse('Process B breached the atomic lock! Race condition possible.');

    $lockProcessA->release();

    $lockProcessC = $store->lock($resourceKey);
    $acquiredC = $lockProcessC->get();

    expect($acquiredC)->toBeTrue('Process C could not acquire lock after release');

    $lockProcessC->release();
});
