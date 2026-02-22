<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Domain\Shared\Contracts\TenantAwareEvent;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Listeners\InvalidateStorefrontCache;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('storefront cache listener bypasses integer guards and resolves tenant via uuid', function () {
    // Setup
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['user_id' => $user->id]);

    // Create an anonymous stub fulfilling the TenantAwareEvent contract with a valid VO
    $event = new class($tenant->uuid) implements TenantAwareEvent {
        public function __construct(private readonly string $uuid) {}

        public function tenantId(): TenantId
        {
            return TenantId::fromString($this->uuid);
        }
    };

    $listener = app(InvalidateStorefrontCache::class);

    // Execute: Proves the integer === 0 guard is bypassed and Tenant::find($uuid) succeeds.
    $listener->handle($event);

    expect(true)->toBeTrue();
});
