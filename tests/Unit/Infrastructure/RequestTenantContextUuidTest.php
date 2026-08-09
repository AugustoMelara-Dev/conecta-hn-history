<?php

declare(strict_types=1);

use App\Infrastructure\Shared\RequestTenantContext;
use Illuminate\Http\Request;

test('request tenant context rejects a numeric active tenant identity', function () {
    $request = Request::create('/');
    $request->setUserResolver(fn (): object => (object) ['tenant_id' => '42']);

    expect(fn () => (new RequestTenantContext($request))->getTenantId())
        ->toThrow(InvalidArgumentException::class);
});

test('request tenant context normalizes a valid UUID', function () {
    $request = Request::create('/');
    $request->setUserResolver(fn (): object => (object) [
        'tenant_id' => 'A25122C0-A33E-4A07-91C2-8B00F4D2859E',
    ]);

    expect((new RequestTenantContext($request))->getTenantId())
        ->toBe('a25122c0-a33e-4a07-91c2-8b00f4d2859e');
});
