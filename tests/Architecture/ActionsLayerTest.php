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
    ->not->toUse('Illuminate\Support\Facades\DB') // Enforce Repository Pattern (except whitelisted actions)
    ->ignoring([
        'App\Actions\Interactions\RecordInteractionAction', // Uses DB::transaction (Allowed pragmatism)
        'App\Actions\Authentication\ResetPasswordAction', // Legacy (Uses Hash/DB implicit)
        'App\Actions\Identity\ReactivateUserAction', // Legacy
    ])
    ->expect('App\Actions')
    ->not->toExtend('Illuminate\Database\Eloquent\Model')
    ->not->toExtend('Illuminate\Console\Command');

test('Actions layer - Strict DTO Usage')
    ->expect('App\Actions')
    ->not->toUse('array') // Soft check: encourage DTOs over arrays in method signatures where possible
    ->ignoring([
        'App\Actions\Interactions\RecordInteractionAction', // Whitelisted for now if it uses sensitive arrays internally
    ]);
