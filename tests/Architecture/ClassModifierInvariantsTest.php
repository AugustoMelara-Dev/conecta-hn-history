<?php

declare(strict_types=1);

test('application handlers are final readonly', function () {
    expect('App\Application')
        ->classes()
        ->toHaveSuffix('Handler')
        ->toBeFinal()
        ->toBeReadonly();
});

test('application validators are final', function () {
    expect('App\Application')
        ->classes()
        ->toHaveSuffix('Validator')
        ->toBeFinal();
});

test('commands do not use md5 hashing', function () {
    expect('App\Application')
        ->not()->toUse('md5');
});

test('product commands implement idempotent interface', function () {
    expect('App\Application\Products\Commands')
        ->classes()
        ->toHaveSuffix('Command')
        ->toImplement(\App\Application\Shared\Contracts\IdempotentCommandInterface::class);
});
