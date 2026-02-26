<?php

test('application layer does not use facades')
    ->expect('App\Application')
    ->not->toUse('Illuminate\Support\Facades');

test('application layer does not use service locators')
    ->expect('App\Application')
    ->not->toUse(['app', 'resolve']);
