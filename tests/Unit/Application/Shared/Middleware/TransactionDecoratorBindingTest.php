<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('confirms the command bus execution is wrapped by the transaction decorator', function () {
// Scaffold strict Tenant Context
$tenantId = 'tnt_' . bin2hex(random_bytes(8));

expect($tenantId)->toBeString();
})->todo('Implement explicit container resolution assertion once the precise ServiceProvider binding for TransactionDecorator is mapped.');
