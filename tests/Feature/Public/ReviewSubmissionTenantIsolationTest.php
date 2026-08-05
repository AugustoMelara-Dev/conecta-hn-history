<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ReviewSubmissionTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_submit_a_review_with_uuid_tenant_ownership(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $product = Product::factory()->forTenant($tenant)->create();

        $this->actingAs($user)
            ->postJson('/reviews', [
                'tenant_id' => (string) $tenant->getKey(),
                'product_id' => $product->getKey(),
                'rating' => 5,
                'comment' => 'Excelente producto.',
                'user_id' => User::factory()->create()->getKey(),
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('reviews', [
            'tenant_id' => (string) $tenant->getKey(),
            'product_id' => $product->getKey(),
            'user_id' => (string) $user->getKey(),
            'rating' => 5,
        ]);

        $message = DB::table('outbox_messages')->first();
        self::assertNotNull($message);
        $payload = json_decode((string) $message->payload, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame((string) $tenant->getKey(), $payload['meta']['tenant_id']);
    }

    public function test_review_rejects_a_product_owned_by_another_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $otherTenant = Tenant::factory()->create(['is_active' => true]);
        $otherProduct = Product::factory()->forTenant($otherTenant)->create();

        $this->actingAs($user)
            ->postJson('/reviews', [
                'tenant_id' => (string) $tenant->getKey(),
                'product_id' => $otherProduct->getKey(),
                'rating' => 4,
                'comment' => 'No debe cruzar tenants.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        $this->assertDatabaseMissing('reviews', [
            'product_id' => $otherProduct->getKey(),
            'user_id' => (string) $user->getKey(),
        ]);
    }
}
