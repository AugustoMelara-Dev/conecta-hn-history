<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BusinessClaimDisputeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_dispute_and_publication_is_paused(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'cafe-identidad',
            'verification_status' => Tenant::VERIFICATION_STATUS_VERIFIED,
            'publication_paused_at' => null,
        ]);

        $response = $this->postJson("/api/v1/business-claims/{$tenant->slug}/disputes", [
            'reason' => 'Posible suplantacion de marca',
            'details' => 'Existe un negocio homonimo con evidencia de titularidad previa.',
            'reporter_email' => 'alerta@example.com',
        ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'open');

        $tenant->refresh();

        $this->assertNotNull($tenant->publication_paused_at);
        $this->assertStringContainsString('Disputa de titularidad abierta', (string) $tenant->publication_pause_reason);

        $this->assertDatabaseHas('business_claim_disputes', [
            'tenant_id' => (string) $tenant->getKey(),
            'status' => 'open',
            'reason' => 'Posible suplantacion de marca',
            'reporter_email' => 'alerta@example.com',
        ]);
    }

    public function test_authenticated_user_is_stored_as_dispute_reporter_when_email_is_not_sent(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'legal-hn',
            'verification_status' => Tenant::VERIFICATION_STATUS_VERIFIED,
            'publication_paused_at' => null,
        ]);
        $user = User::factory()->create([
            'email' => 'owner@example.com',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson("/api/v1/business-claims/{$tenant->slug}/disputes", [
                'reason' => 'Reclamo de titularidad',
            ]);

        $response->assertStatus(202);

        $this->assertDatabaseHas('business_claim_disputes', [
            'tenant_id' => (string) $tenant->getKey(),
            'reporter_user_id' => (string) $user->getKey(),
            'reporter_email' => $user->email,
        ]);
    }
}
