<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SalesExportDownloadSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function signed_export_download_rejects_paths_outside_the_signed_tenant_scope(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenantA->id,
        ]);

        $url = URL::temporarySignedRoute('api.exports.download', now()->addMinutes(5), [
            'tenant' => (string) $tenantA->id,
            'path' => base64_encode("tenants/{$tenantB->id}/reports/private.xlsx"),
        ]);

        $this->actingAs($user)
            ->getJson($url)
            ->assertForbidden();
    }
}
