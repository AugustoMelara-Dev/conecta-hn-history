<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Category;
use App\Models\Interaction;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AnalyticsTrackFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_tracking_endpoint_accepts_uuid_tenant_and_creates_live_lead_flow(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $category = Category::factory()->create([
            'name' => 'Salud',
            'slug' => 'salud',
        ]);

        $tenant = Tenant::factory()->pro()->state([
            'user_id' => $owner->id,
            'name' => 'Clinica Sonrisa Dental',
            'slug' => 'clinica-sonrisa-track',
            'business_type' => 'service',
            'category_id' => $category->getKey(),
            'is_active' => true,
            'subscription_status' => 'active',
        ])->create();

        $response = $this->postJson('/api/v1/analytics/track', [
            'tenant_id' => (string) $tenant->getKey(),
            'type' => 'whatsapp_click',
            'metadata' => [
                'message' => 'Necesito una valoracion dental hoy',
            ],
        ]);

        $response
            ->assertAccepted()
            ->assertJson([
                'success' => true,
            ]);

        $interaction = Interaction::withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->latest('id')
            ->first();

        $this->assertNotNull($interaction);
        $this->assertSame('whatsapp_click', $interaction->type);
        $this->assertNotEmpty($interaction->visitor_id);
        $this->assertSame($interaction->visitor_id, $interaction->session_id);

        $lead = Lead::withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->latest('id')
            ->first();

        $this->assertNotNull($lead);
        $this->assertSame('Lead desde WhatsApp', $lead->name);
        $this->assertSame('whatsapp_click', $lead->source);
        $this->assertSame('Necesito una valoracion dental hoy', $lead->message);
        $this->assertSame($interaction->session_id, $lead->session_id);
    }
}
