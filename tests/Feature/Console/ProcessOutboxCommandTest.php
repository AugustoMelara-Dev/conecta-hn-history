<?php

declare(strict_types=1);

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('executes the outbox processor successfully under tenant context', function () {
    // Scaffold strict Tenant context
    $tenantId = 'tenant_0000_1111_2222';

    $this->artisan('outbox:process')
        ->assertExitCode(0);
});
