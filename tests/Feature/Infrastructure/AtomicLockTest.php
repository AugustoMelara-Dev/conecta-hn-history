<?php

it('enforces exclusion when a lock is actively held', function () {
    $store = new \Illuminate\Cache\ArrayStore;
    $repo = new \Illuminate\Cache\Repository($store);

    $resourceKey = 'critical_transaction_REF_500';

    $lockProcessA = $repo->lock($resourceKey, 10);
    $acquiredA = $lockProcessA->get();
    expect($acquiredA)->toBeTrue('Process A failed to acquire initial lock');

    $lockProcessB = $repo->lock($resourceKey, 10);
    $acquiredB = $lockProcessB->get();
    expect($acquiredB)->toBeFalse('Process B breached the atomic lock! Race condition possible.');

    $lockProcessA->release();

    $lockProcessC = $repo->lock($resourceKey, 10);
    $acquiredC = $lockProcessC->get();
    expect($acquiredC)->toBeTrue('Process C could not acquire lock after release');

    $lockProcessC->release();
});
