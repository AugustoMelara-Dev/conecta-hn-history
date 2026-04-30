<?php

declare(strict_types=1);

use App\Broadcasting\ChannelRegistry;
use Illuminate\Broadcasting\PrivateChannel;

test('tenant admin channels accept uuid tenant identifiers', function () {
    $tenantId = '019ddc71-5c6c-7055-9e65-cd36cbfa25f4';

    $channel = ChannelRegistry::tenantAdmins($tenantId);

    expect($channel)
        ->toBeInstanceOf(PrivateChannel::class)
        ->and((string) $channel)->toBe("private-tenant.{$tenantId}.admins");
});

test('tenant admin channels reject empty tenant identifiers', function () {
    ChannelRegistry::tenantAdmins('');
})->throws(\InvalidArgumentException::class, 'tenantId must be a non-empty string');
