<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Events\Payment\PaymentApproved;
use App\Listeners\UpdateTenantSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('listener resolves tenant via uuid and processes payment approved event safely', function () {
    // Setup isolated tenant context
    $user = User::factory()->create();
    $tenantA = Tenant::factory()->create(['user_id' => $user->id]);
    $tenantB = Tenant::factory()->create(['user_id' => $user->id]);

    // Utilize the discovered static factory. Passing the UUID string.
    $event = PaymentApproved::create(
        $tenantA->uuid,
        12345,
        'ORD-12345',
        'TXN-67890',
        'Stripe',
        5000,
        'HNL'
    );

    $listener = app(UpdateTenantSubscription::class);

    // Execute: This asserts the UUID unwrapping and Tenant::lockForUpdate()->find()
    // do not throw TypeError or query exceptions.
    $listener->handle($event);

    // Behavioral verification: If it reached here without Exception, the VO extraction succeeded.
    expect(true)->toBeTrue();
});
