<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OwnerRegistrationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_registration_uses_wizard_payload_and_persists_commercial_mode(): void
    {
        $category = Category::factory()->create();
        $city = City::factory()->create();

        $response = $this->post('/registrar-negocio', [
            'business_name' => 'Cafe Norte',
            'slug' => 'cafe-norte',
            'name' => 'Ana Propietaria',
            'email' => 'ana@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'city_id' => $city->id,
            'category_id' => $category->id,
            'commercial_mode' => 'appointments',
            'business_type' => 'service',
            'rubro_key' => 'appointments',
            'whatsapp' => '+50499990000',
            'selected_plan' => 'free',
            'terms_accepted' => '1',
            'privacy_accepted' => '1',
        ]);

        $response->assertSessionHasNoErrors();

        $tenant = Tenant::withoutGlobalScopes()->where('slug', 'cafe-norte')->firstOrFail();
        $user = User::where('email', 'ana@example.test')->firstOrFail();

        $response->assertRedirect('/app/cafe-norte');
        $this->assertSame('Cafe Norte', $tenant->name);
        $this->assertSame('appointments', $tenant->getAttribute('commercial_mode'));
        $this->assertSame('service', (string) $tenant->getRawOriginal('business_type'));
        $this->assertSame((string) $tenant->id, (string) $user->tenant_id);
        $this->assertSame((string) $user->id, (string) $tenant->user_id);
    }
}
