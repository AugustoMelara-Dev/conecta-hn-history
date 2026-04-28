<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class CheckoutSessionNonceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cart_summary_rejects_missing_checkout_nonce(): void
    {
        $tenant = Tenant::factory()->create();
        $product = Product::factory()->forTenant($tenant)->create();

        $session = $this->postJson('/api/v1/checkout/session', [
            'tenant' => (string) $tenant->id,
        ])->assertCreated();

        $response = $this->postJson('/api/v1/checkout/cart-summary', [
            'tenant' => (string) $tenant->id,
            'session_id' => $session->json('session_id'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(419)
            ->assertJsonPath('message', 'Invalid checkout session.');
    }

    public function test_cart_summary_accepts_server_issued_checkout_nonce(): void
    {
        $tenant = Tenant::factory()->create();
        $product = Product::factory()->forTenant($tenant)->create(['price' => 125]);

        $session = $this->postJson('/api/v1/checkout/session', [
            'tenant' => (string) $tenant->id,
        ])->assertCreated();

        $response = $this->withHeader('X-Checkout-Nonce', (string) $session->json('checkout_nonce'))
            ->postJson('/api/v1/checkout/cart-summary', [
                'tenant' => (string) $tenant->id,
                'session_id' => $session->json('session_id'),
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('subtotal', 250);
    }
}
