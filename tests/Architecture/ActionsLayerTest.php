<?php

declare(strict_types=1);

test('Actions layer - Semantics and Immutability', function () {
    expect('App\Actions')
        ->toBeFinal()
        ->toBeReadonly()
        ->classes()->not()->toBeAbstract();
});

test('Actions layer - Framework Decoupling', function () {
    expect('App\Actions')
        ->not()->toUse('Illuminate\Http\Request')
        ->not()->toUse('Illuminate\Http\RedirectResponse')
        ->not()->toUse('Illuminate\Http\JsonResponse');
});

test('Actions layer - Domain Purity', function () {
    expect('App\Actions')
        ->not()->toUse('Illuminate\Support\Facades\DB')
        ->not()->toUse('Illuminate\Support\Facades\Hash')
        ->not()->toUse('Illuminate\Support\Facades\Log')
        ->not()->toUse('Illuminate\Support\Facades\Cache');

    expect('App\Actions')
        ->not()->toExtend('Illuminate\Database\Eloquent\Model')
        ->not()->toExtend('Illuminate\Console\Command');
});

test('Actions layer - Strict DTO Usage', function () {
    expect('App\Actions')
        ->not()->toUse('array');
});

test('Actions layer - Handler Decoupling', function () {
    expect('App\Actions')
        ->not()->toUse('App\Application\Identity\Handlers');
});
