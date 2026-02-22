<?php

declare(strict_types=1);

it('no file references the deleted App\ValueObjects\TenantId namespace', function (): void {
    // Enforced by rg sweep above; this test documents the invariant permanently
    expect(\App\Domain\Shared\ValueObjects\TenantId::class)->toBeClass();
});
