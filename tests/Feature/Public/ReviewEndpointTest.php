<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Http\Middleware\CheckMaintenanceMode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReviewEndpointTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_returns_an_inertia_compliant_redirect_after_submitting_a_review(): void
    {
        $this->withoutMiddleware(CheckMaintenanceMode::class);
        $token = 'review-submit-token';

        $user = User::factory()->create();

        $tenantId = Str::uuid()->toString();
        $tenant = Tenant::forceCreate([
            'id' => $tenantId,
            'name' => 'Smoke Test Tenant',
            'slug' => 'smoke-test-tenant',
        ]);

        $response = $this->withSession(['_token' => $token])
            ->actingAs($user)
            ->from('/')
            ->post(route('reviews.store'), [
                '_token' => $token,
                'tenant_id' => $tenant->id,
                'rating' => 5,
                'comment' => 'Integration test review',
            ]);

        $response->assertStatus(303);
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reviews', [
            'tenant_id' => $tenant->id,
            'rating' => 5,
            'is_approved' => false,
        ]);
    }
}
