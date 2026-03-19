<?php

it('ensures the application layer does not depend on the HTTP layer', function () {
    expect('App\Application')
        ->not()->toUse('Illuminate\Http\Request')
        ->not()->toUse('Illuminate\Http\Response')
        ->not()->toUse('Illuminate\Support\Facades\Request');
});
