<?php

use function Pest\Laravel\get;

test('example', function () {
    $response = get('/');

    $response->assertStatus(200);
});
