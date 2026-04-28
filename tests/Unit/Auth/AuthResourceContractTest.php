<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Domain\Identity\Enums\UserRole;
use App\Http\Resources\V1\AuthResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

final class AuthResourceContractTest extends TestCase
{
    use DatabaseTransactions;

    public function test_auth_resource_infers_surface_contract_from_user_context(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'tenant_id' => null,
        ]);

        $resource = new AuthResource((object) [
            'user' => $customer,
            'accessToken' => 'tok_test',
        ]);

        $payload = $resource->toArray(Request::create('/api/v1/auth/social', 'POST'));

        $this->assertSame(route('directory.index', [], false), $payload['redirect_to']);
        $this->assertSame('user_app', $payload['home_surface']);
        $this->assertSame('customer', $payload['audience']);
    }

    public function test_auth_resource_honors_explicit_contract_fields_when_present(): void
    {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'negocio-contract',
        ]);
        $owner->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();

        $resource = new AuthResource((object) [
            'user' => $owner,
            'accessToken' => 'tok_business',
            'redirectUrl' => '/app',
            'homeSurface' => 'business_app',
            'audience' => 'owner',
        ]);

        $payload = $resource->toArray(Request::create('/api/v1/auth/social', 'POST'));

        $this->assertSame('/app', $payload['redirect_to']);
        $this->assertSame('business_app', $payload['home_surface']);
        $this->assertSame('owner', $payload['audience']);
    }
}
