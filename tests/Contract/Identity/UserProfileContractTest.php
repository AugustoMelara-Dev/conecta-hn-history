<?php

declare(strict_types=1);

namespace Tests\Contract\Identity;

use App\Application\Identity\ViewModels\UserProfileViewModel;
use Tests\Contract\ContractTestCase;

final class UserProfileContractTest extends ContractTestCase
{
    public function test_user_profile_matches_json_schema_contract(): void
    {
        $viewModel = new UserProfileViewModel(
            id: '01942f8a-1234-7890-abcd-ef1234567890',
            tenantId: '01942f8b-5678-7890-abcd-ef1234567890',
            name: 'Jane Beneficiary',
            email: 'jane@contract.test',
            tenantName: 'Jane LLC',
            tenantSlug: 'jane-llc',
            createdAt: '2026-01-15T22:00:00+00:00',
        );

        $this->assertMatchesJsonSchema('user_profile', $viewModel->toArray());
    }

    public function test_user_profile_without_tenant_matches_schema(): void
    {
        $viewModel = new UserProfileViewModel(
            id: '01942f8a-1234-7890-abcd-ef1234567890',
            tenantId: '',
            name: 'Orphan User',
            email: 'orphan@contract.test',
            tenantName: null,
            tenantSlug: null,
            createdAt: '2026-01-15T22:00:00+00:00',
        );

        $responseData = $viewModel->toArray();

        $this->assertMatchesJsonSchema('user_profile', $responseData);
        $this->assertNull($responseData['tenantName']);
        $this->assertNull($responseData['tenantSlug']);
    }
}
