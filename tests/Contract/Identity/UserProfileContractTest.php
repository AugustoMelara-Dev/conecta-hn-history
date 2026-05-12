<?php

declare(strict_types=1);

namespace Tests\Contract\Identity;

use App\Application\Identity\Queries\GetUserProfileQuery;
use App\Application\Identity\QueryHandlers\GetUserProfileHandler;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Contract\ContractTestCase;

class UserProfileContractTest extends ContractTestCase
{
    use RefreshDatabase;

    public function test_user_profile_matches_json_schema_contract(): void
    {
        // 1. Arrange: Setup Database with Raw SQL (Bypassing Factories for speed/purity)
        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Jane Beneficiary',
            'email' => 'jane@contract.test',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'id' => $tenantId,
            'user_id' => $userId,
            'name' => 'Jane LLC',
            'slug' => 'jane-llc',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Link User to Tenant
        DB::table('users')->where('id', $userId)->update(['tenant_id' => $tenantId]);

        // 2. Act: Execute Query Handler
        $handler = app(GetUserProfileHandler::class);
        $query = new GetUserProfileQuery($userId, $tenantId);
        $viewModel = $handler->handle($query);

        $responseData = [
            'id' => (string) $viewModel->id,
            'name' => (string) $viewModel->name,
            'email' => (string) $viewModel->email,
            'tenantName' => null,
            'tenantSlug' => null,
            'formattedCreatedAt' => (string) $viewModel->created_at,
        ];

        // 3. Assert: Validate against Schema (Logical Name)
        $this->assertMatchesJsonSchema('user_profile', $responseData);
    }

    public function test_user_profile_without_tenant_is_rejected_by_tenant_firewall(): void
    {
        // 1. Arrange: User without Tenant
        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Orphan User',
            'email' => 'orphan@contract.test',
            'password' => 'secret',
            'tenant_id' => null, // Explicitly null
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Act + Assert: strict tenant isolation now requires explicit tenant_id.
        $handler = app(GetUserProfileHandler::class);
        $query = new GetUserProfileQuery($userId, (string) Str::uuid());

        $this->expectException(UserNotFoundException::class);
        $handler->handle($query);
    }
}
