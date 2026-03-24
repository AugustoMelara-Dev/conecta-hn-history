<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\City;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BusinessRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_registers_a_business_without_hitting_container_resolution_errors(): void
    {
        /** @var City $city */
        $city = City::factory()->create();
        /** @var Category $category */
        $category = Category::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->post('/registrar-negocio', [
            'business_name' => 'Comercial Yoly',
            'slug' => 'comercial-yoly',
            'name' => 'Jose Melara',
            'email' => 'melarajose83@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'city_id' => $city->id,
            'category_id' => $category->id,
            'whatsapp' => '+50496321907',
            'selected_plan' => 'free',
            'terms_accepted' => '1',
        ]);

        $response->assertRedirect(route('verification.notice', [], false));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('tenants', [
            'slug' => 'comercial-yoly',
            'city_id' => $city->id,
            'category_id' => $category->id,
        ]);
        $this->assertDatabaseHas('tenant_memberships', [
            'user_id' => \App\Models\User::query()->where('email', 'melarajose83@example.com')->value('id'),
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('locations', [
            'slug' => 'principal',
            'name' => 'Principal',
            'is_primary' => true,
        ]);
    }
}
