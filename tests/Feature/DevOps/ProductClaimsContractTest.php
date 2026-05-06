<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Tests\TestCase;

final class ProductClaimsContractTest extends TestCase
{
    public function test_readme_declares_honest_crm_scope(): void
    {
        $readme = file_get_contents(base_path('README.md'));

        $this->assertIsString($readme);
        $this->assertStringContainsString('## Alcance CRM actual (honesto)', $readme);
        $this->assertStringContainsString('No debe interpretarse como CRM enterprise completo.', $readme);
        $this->assertStringNotContainsString('CRM completo', $readme);
        $this->assertStringNotContainsString('production-ready', $readme);
        $this->assertStringNotContainsString('listo para producción', $readme);
    }

    public function test_crm_api_contract_declares_non_enterprise_scope(): void
    {
        $contract = file_get_contents(base_path('docs/api/crm-v1.md'));

        $this->assertIsString($contract);
        $this->assertStringContainsString('## Honest scope note', $contract);
        $this->assertStringContainsString('Still not included in this contract:', $contract);
    }
}

