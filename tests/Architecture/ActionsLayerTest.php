<?php

declare(strict_types=1);

test('Actions layer - Semantics and Immutability')
    ->expect('App\Actions')
    ->toBeFinal()
    ->toBeReadonly()
    ->classes()->not->toBeAbstract();

test('Actions layer - Framework Decoupling')
    ->expect('App\Actions')
    ->not->toUse('Illuminate\Http\Request')
    ->not->toUse('Illuminate\Http\RedirectResponse')
    ->not->toUse('Illuminate\Http\JsonResponse');

test('Actions layer - Domain Purity')
    ->expect('App\Actions')
    ->not->toUse('Illuminate\Support\Facades\DB') // Strict: Zero exceptions. All Actions delegate via CommandBus.
    ->expect('App\Actions')
    ->not->toExtend('Illuminate\Database\Eloquent\Model')
    ->not->toExtend('Illuminate\Console\Command');

test('Actions layer - Strict DTO Usage')
    ->expect('App\Actions')
    ->not->toUse('array'); // Strict: All Actions use typed DTOs/Commands, not raw arrays.
