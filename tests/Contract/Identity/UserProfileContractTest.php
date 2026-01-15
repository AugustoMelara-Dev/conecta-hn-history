<?php

declare(strict_types=1);

namespace Tests\Contract\Identity;

use App\Application\Identity\Queries\GetUserProfileQuery;
use App\Application\Identity\QueryHandlers\GetUserProfileHandler;
use App\Application\Identity\ViewModels\UserProfileViewModel;
use App\Domain\Identity\Entities\UserEntity;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Contract\ContractTestCase;

class UserProfileContractTest extends ContractTestCase
{
    public function test_user_profile_matches_json_schema_contract(): void
    {
        // 1. Arrange: Setup Database with Raw SQL (Bypassing Factories for speed/purity)
        $userId = DB::table('users')->insertGetId([
            'name' => 'Jane Beneficiary',
            'email' => 'jane@contract.test',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenantId = DB::table('tenants')->insertGetId([
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
        $handler = new GetUserProfileHandler();
        $query = new GetUserProfileQuery($userId);
        $viewModel = $handler->handle($query);

        // Serialize to Array
        $responseData = json_decode(json_encode($viewModel), true);

        // 3. Assert: Validate against Schema (Logical Name)
        $this->assertMatchesJsonSchema('user_profile', $responseData);
    }

    public function test_user_profile_without_tenant_matches_schema(): void
    {
        // 1. Arrange: User without Tenant
        $userId = DB::table('users')->insertGetId([
            'name' => 'Orphan User',
            'email' => 'orphan@contract.test',
            'password' => 'secret',
            'tenant_id' => null, // Explicitly null
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Act
        $handler = new GetUserProfileHandler();
        $query = new GetUserProfileQuery($userId);
        $viewModel = $handler->handle($query);

        $responseData = json_decode(json_encode($viewModel), true);

        // 3. Assert
        $this->assertMatchesJsonSchema('user_profile', $responseData);
        
        // Manual assertion to verify schema allowed nulls
        $this->assertNull($responseData['tenantName']);
    }
}
