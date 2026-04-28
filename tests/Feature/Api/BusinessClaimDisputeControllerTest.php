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

    public function test_guest_cannot_open_dispute_or_pause_publication(): void
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
            ->assertUnauthorized();

        $tenant->refresh();

        $this->assertNull($tenant->publication_paused_at);
        $this->assertNull($tenant->publication_pause_reason);
        $this->assertDatabaseMissing('business_claim_disputes', [
            'tenant_id' => (string) $tenant->getKey(),
            'reason' => 'Posible suplantacion de marca',
        ]);
    }

    public function test_authenticated_non_owner_can_report_without_pausing_publication(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'cafe-identidad',
            'verification_status' => Tenant::VERIFICATION_STATUS_VERIFIED,
            'publication_paused_at' => null,
        ]);
        $reporter = User::factory()->create([
            'email' => 'alerta@example.com',
        ]);

        $response = $this
            ->actingAs($reporter, 'sanctum')
            ->postJson("/api/v1/business-claims/{$tenant->slug}/disputes", [
                'reason' => 'Posible suplantacion de marca',
                'details' => 'Existe un negocio homonimo con evidencia de titularidad previa.',
            ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.publication_paused', false);

        $tenant->refresh();

        $this->assertNull($tenant->publication_paused_at);

        $this->assertDatabaseHas('business_claim_disputes', [
            'tenant_id' => (string) $tenant->getKey(),
            'status' => 'open',
            'reason' => 'Posible suplantacion de marca',
            'reporter_email' => 'alerta@example.com',
        ]);
    }

    public function test_owner_can_open_dispute_and_pause_publication(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'legal-pausa',
            'verification_status' => Tenant::VERIFICATION_STATUS_VERIFIED,
            'publication_paused_at' => null,
        ]);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business-claims/{$tenant->slug}/disputes", [
                'reason' => 'Titularidad en revision',
            ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('data.publication_paused', true);

        $tenant->refresh();

        $this->assertNotNull($tenant->publication_paused_at);
        $this->assertStringContainsString('Disputa de titularidad abierta', (string) $tenant->publication_pause_reason);
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
