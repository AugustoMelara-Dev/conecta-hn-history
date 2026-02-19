<?php

declare(strict_types=1);

arch('application handlers are final readonly')
    ->expect('App\Application')
    ->classes()
    ->toHaveSuffix('Handler')
    ->toBeFinal()
    ->toBeReadonly();

arch('application validators are final')
    ->expect('App\Application')
    ->classes()
    ->toHaveSuffix('Validator')
    ->toBeFinal();

arch('commands do not use md5 hashing')
    ->expect('App\Application')
    ->not->toUse('md5');

arch('product commands implement idempotent interface')
    ->expect('App\Application\Products\Commands')
    ->classes()
    ->toHaveSuffix('Command')
    ->toImplement(\App\Application\Shared\Contracts\IdempotentCommandInterface::class);
