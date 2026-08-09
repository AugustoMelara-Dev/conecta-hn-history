<?php

declare(strict_types=1);

use App\Application\Ai\PrivateAiAccessGate;
use App\Domain\Shared\Services\TenantContext;
use App\Http\Controllers\Api\V1\AiController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Queue::fake();
});

test('generate denies tenants outside the private AI gate', function (): void {
    $user = User::factory()->make([
        'id' => (string) Str::uuid(),
    ]);
    $user->tenant_id = (string) Str::uuid();

    $privateAiAccess = Mockery::mock(PrivateAiAccessGate::class);
    $privateAiAccess
        ->shouldReceive('allows')
        ->once()
        ->with($user, (string) $user->tenant_id)
        ->andReturnFalse();

    $request = Request::create('/api/v1/ai/generate', 'POST', [
        'business_name' => 'Cafe Tegus',
        'activity' => 'Cafe',
        'location' => 'Tegucigalpa',
    ]);
    $request->setUserResolver(fn () => $user);

    $tenantContext = new TenantContext;
    $tenantContext->setTenant((string) $user->tenant_id);
    $response = (new AiController($privateAiAccess, $tenantContext))->generate($request);

    expect($response->getStatusCode())->toBe(403);
    Queue::assertNothingPushed();
});
