<?php

test('application layer does not use facades', function () {
    expect('App\Application')
        ->not()->toUse('Illuminate\Support\Facades');
});

test('application layer does not use service locators', function () {
    expect('App\Application')
        ->not()->toUse(['app', 'resolve']);
});
