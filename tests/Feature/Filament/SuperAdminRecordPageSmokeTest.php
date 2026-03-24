<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Models\Category;
use App\Models\City;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class SuperAdminRecordPageSmokeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_superadmin_record_pages_render_with_real_data(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $city = City::factory()->create(['name' => 'Tegucigalpa']);
        $category = Category::factory()->create(['name' => 'Restaurantes']);
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
            'email' => 'dueno.negocio@example.com',
        ]);

        $tenant = Tenant::factory()->create([
            'user_id' => $owner->getKey(),
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
            'business_type' => BusinessType::RETAIL->value,
            'name' => 'Cafe Norte QA',
            'slug' => 'cafe-norte-qa-admin',
        ]);

        $owner->forceFill(['tenant_id' => $tenant->getKey()])->save();

        $operator = User::factory()->create([
            'role' => UserRole::STAFF,
            'email' => 'operador.soporte@example.com',
        ]);

        $payment = new SubscriptionPayment([
            'amount' => 799.00,
            'bank' => 'BAC',
            'reference_number' => 'REF-QA-001',
            'proof_path' => 'payments/proofs/ref-qa-001.jpg',
            'status' => SubscriptionPayment::STATUS_PENDING,
        ]);
        $payment->forceFill(['tenant_id' => $tenant->getKey()])->save();

        $this->actingAs($superAdmin);
        Filament::setCurrentPanel('admin');

        $this->get(route('filament.admin.resources.tenants.edit', ['record' => $tenant], false))
            ->assertOk()
            ->assertSee('Nombre del Negocio')
            ->assertSee('Configuración de plan')
            ->assertSee('Cafe Norte QA');

        $this->get(route('filament.admin.resources.users.edit', ['record' => $operator], false))
            ->assertOk()
            ->assertSee('Contraseña')
            ->assertSee('operador.soporte@example.com', false);

        $this->get(route('filament.admin.resources.categories.edit', ['record' => $category], false))
            ->assertOk()
            ->assertSee('Restaurantes');

        $this->get(route('filament.admin.resources.cities.edit', ['record' => $city], false))
            ->assertOk()
            ->assertSee('Tegucigalpa');

        $this->get(route('filament.admin.resources.payments.view', ['record' => $payment], false))
            ->assertOk()
            ->assertSee('Información del Pago')
            ->assertSee('REF-QA-001');
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    private function makeSuperAdmin(): User
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'email' => 'superadmin.records@example.com',
        ]);

        config(['auth.super_admins' => [$superAdmin->email]]);

        return $superAdmin;
    }
}
