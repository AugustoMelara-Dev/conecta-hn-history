<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Models\AiToolConfirmation;
use App\Models\City;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AiToolConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_routes_require_authentication(): void
    {
        $this->postJson('/api/v1/tenant/agent/tools/prepare', [])
            ->assertUnauthorized();
    }

    public function test_confirmation_attempts_are_rate_limited_per_actor_and_tenant(): void
    {
        [$owner] = $this->ownerTenant();
        config()->set('ai.tool_confirmations.requests_per_minute', 2);
        Sanctum::actingAs($owner);

        foreach (range(1, 2) as $attempt) {
            $this->withHeader('Idempotency-Key', "rate-limit-{$attempt}")
                ->postJson('/api/v1/tenant/agent/tools/prepare', [
                    'tool' => 'tenant_lead.prepare',
                    'payload' => ['name' => "Prospecto {$attempt}", 'phone' => "9999-000{$attempt}"],
                ])
                ->assertCreated();
        }

        $this->withHeader('Idempotency-Key', 'rate-limit-3')
            ->postJson('/api/v1/tenant/agent/tools/prepare', [
                'tool' => 'tenant_lead.prepare',
                'payload' => ['name' => 'Prospecto 3', 'phone' => '9999-0003'],
            ])
            ->assertTooManyRequests();
    }

    public function test_lead_is_only_created_after_actor_bound_single_use_confirmation(): void
    {
        [$owner, $tenant] = $this->ownerTenant();
        Sanctum::actingAs($owner);

        $prepared = $this->withHeader('Idempotency-Key', 'lead-confirm-001')
            ->postJson('/api/v1/tenant/agent/tools/prepare', [
                'tool' => 'tenant_lead.prepare',
                'payload' => [
                    'name' => 'Cliente potencial',
                    'email' => 'cliente@example.test',
                    'message' => 'Quiere una cotizacion.',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('confirmation.tool', 'tenant_lead.prepare')
            ->assertJsonPath('confirmation.status', 'pending');

        $confirmationId = (string) $prepared->json('confirmation.id');
        $token = (string) $prepared->json('confirmation.confirmation_token');
        self::assertSame(64, strlen($token));
        self::assertSame(0, DB::table('leads')->where('tenant_id', $tenant->id)->count());

        $raw = DB::table('ai_tool_confirmations')->where('id', $confirmationId)->first();
        self::assertNotNull($raw);
        self::assertStringNotContainsString('Cliente potencial', (string) $raw->payload);
        self::assertStringNotContainsString($token, (string) $raw->confirmation_token);

        $confirmed = $this->postJson("/api/v1/tenant/agent/tools/{$confirmationId}/confirm", [
            'confirmation_token' => $token,
        ])->assertOk()
            ->assertJsonPath('replayed', false)
            ->assertJsonPath('confirmation.status', 'completed')
            ->assertJsonPath('result.type', 'lead');

        $leadId = (int) $confirmed->json('result.lead_id');
        $this->assertDatabaseHas('leads', [
            'id' => $leadId,
            'tenant_id' => $tenant->id,
            'name' => 'Cliente potencial',
            'source' => 'private_ai_confirmed',
        ]);

        $this->postJson("/api/v1/tenant/agent/tools/{$confirmationId}/confirm", [
            'confirmation_token' => $token,
        ])->assertOk()
            ->assertJsonPath('replayed', true)
            ->assertJsonPath('result.lead_id', $leadId);

        self::assertSame(1, DB::table('leads')->where('tenant_id', $tenant->id)->count());
    }

    public function test_prepare_is_idempotent_and_rejects_payload_collision(): void
    {
        [$owner, $tenant] = $this->ownerTenant();
        Sanctum::actingAs($owner);
        $headers = ['Idempotency-Key' => 'lead-confirm-idempotent'];

        $first = $this->withHeaders($headers)->postJson('/api/v1/tenant/agent/tools/prepare', [
            'tool' => 'tenant_lead.prepare',
            'payload' => ['name' => 'Misma persona', 'phone' => '9999-0000'],
        ])->assertCreated();

        $this->withHeaders($headers)->postJson('/api/v1/tenant/agent/tools/prepare', [
            'tool' => 'tenant_lead.prepare',
            'payload' => ['phone' => '9999-0000', 'name' => 'Misma persona'],
        ])->assertOk()
            ->assertJsonPath('replayed', true)
            ->assertJsonPath('confirmation.id', $first->json('confirmation.id'))
            ->assertJsonPath(
                'confirmation.confirmation_token',
                $first->json('confirmation.confirmation_token'),
            );

        $this->withHeaders($headers)->postJson('/api/v1/tenant/agent/tools/prepare', [
            'tool' => 'tenant_lead.prepare',
            'payload' => ['name' => 'Payload cambiado', 'phone' => '9999-0000'],
        ])->assertConflict()
            ->assertJsonPath('code', 'idempotency_conflict');

        self::assertSame(1, DB::table('ai_tool_confirmations')->where('tenant_id', $tenant->id)->count());
    }

    public function test_foreign_actor_invalid_token_expiry_and_cancellation_fail_closed(): void
    {
        [$ownerA, $tenantA] = $this->ownerTenant();
        [$ownerB, $tenantB] = $this->ownerTenant();
        config()->set('ai.private.tenant_allowlist', [(string) $tenantA->id, (string) $tenantB->id]);

        Sanctum::actingAs($ownerA);
        $prepared = $this->withHeader('Idempotency-Key', 'lead-hostile-001')
            ->postJson('/api/v1/tenant/agent/tools/prepare', [
                'tool' => 'tenant_lead.prepare',
                'payload' => ['name' => 'No filtrar', 'phone' => '9999-1111'],
            ])->assertCreated();
        $id = (string) $prepared->json('confirmation.id');
        $token = (string) $prepared->json('confirmation.confirmation_token');

        $this->postJson("/api/v1/tenant/agent/tools/{$id}/confirm", [
            'confirmation_token' => str_repeat('x', 64),
        ])->assertForbidden()
            ->assertJsonPath('code', 'invalid_confirmation_token');

        Sanctum::actingAs($ownerB);
        $this->postJson("/api/v1/tenant/agent/tools/{$id}/confirm", [
            'confirmation_token' => $token,
        ])->assertNotFound();

        Sanctum::actingAs($ownerA);
        DB::table('ai_tool_confirmations')->where('id', $id)->update(['expires_at' => now()->subMinute()]);
        $this->postJson("/api/v1/tenant/agent/tools/{$id}/confirm", [
            'confirmation_token' => $token,
        ])->assertGone()
            ->assertJsonPath('code', 'confirmation_expired');
        $this->assertDatabaseHas('ai_tool_confirmations', [
            'id' => $id,
            'status' => AiToolConfirmation::STATUS_EXPIRED,
        ]);
        self::assertSame(0, DB::table('leads')->where('tenant_id', $tenantA->id)->count());

        $cancelPrepared = $this->withHeader('Idempotency-Key', 'lead-cancel-001')
            ->postJson('/api/v1/tenant/agent/tools/prepare', [
                'tool' => 'tenant_lead.prepare',
                'payload' => ['name' => 'Cancelar', 'phone' => '9999-2222'],
            ])->assertCreated();
        $cancelId = (string) $cancelPrepared->json('confirmation.id');
        $cancelToken = (string) $cancelPrepared->json('confirmation.confirmation_token');

        $this->postJson("/api/v1/tenant/agent/tools/{$cancelId}/cancel", [
            'confirmation_token' => $cancelToken,
        ])->assertOk()
            ->assertJsonPath('confirmation.status', 'cancelled');
        $this->postJson("/api/v1/tenant/agent/tools/{$cancelId}/confirm", [
            'confirmation_token' => $cancelToken,
        ])->assertConflict()
            ->assertJsonPath('code', 'confirmation_cancelled');
        self::assertSame(0, DB::table('leads')->where('tenant_id', $tenantA->id)->count());
    }

    public function test_confirmed_order_uses_canonical_server_pricing_inventory_and_replay(): void
    {
        [$owner, $tenant] = $this->ownerTenant();
        $product = Product::factory()
            ->forTenant($tenant)
            ->withStock(10)
            ->create(['price' => 125.50, 'is_active' => true, 'is_visible' => true]);
        Sanctum::actingAs($owner);

        $prepared = $this->withHeader('Idempotency-Key', 'order-confirm-001')
            ->postJson('/api/v1/tenant/agent/tools/prepare', [
                'tool' => 'commerce.order.create',
                'payload' => [
                    'customer_name' => 'Cliente confirmado',
                    'customer_phone' => '9999-3333',
                    'delivery_type' => 'pickup',
                    'payment_method' => 'cash',
                    'items' => [[
                        'product_id' => $product->id,
                        'quantity' => 2,
                    ]],
                ],
            ])->assertCreated()
            ->assertJsonPath('confirmation.tool', 'commerce.order.create')
            ->assertJsonPath('confirmation.preview.quote.currency', 'HNL')
            ->assertJsonPath('confirmation.preview.quote.items.0.product_name', $product->name)
            ->assertJsonPath('confirmation.preview.quote.items.0.unit_price', 125.5)
            ->assertJsonPath('confirmation.preview.quote.total', 251);
        $id = (string) $prepared->json('confirmation.id');
        $token = (string) $prepared->json('confirmation.confirmation_token');

        self::assertSame(0, DB::table('orders')->where('tenant_id', $tenant->id)->count());
        self::assertSame(10, (int) DB::table('products')->where('tenant_id', $tenant->id)->where('id', $product->id)->value('stock_quantity'));

        $confirmed = $this->postJson("/api/v1/tenant/agent/tools/{$id}/confirm", [
            'confirmation_token' => $token,
        ])->assertOk()
            ->assertJsonPath('replayed', false)
            ->assertJsonPath('result.type', 'order')
            ->assertJsonPath('result.total', 251);
        $orderId = (int) $confirmed->json('result.order_id');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'tenant_id' => $tenant->id,
            'customer_name' => 'Cliente confirmado',
            'total' => 251,
            'payment_status' => 'pending',
            'source' => 'private_ai_confirmed',
        ]);
        self::assertSame(8, (int) DB::table('products')->where('tenant_id', $tenant->id)->where('id', $product->id)->value('stock_quantity'));

        $this->postJson("/api/v1/tenant/agent/tools/{$id}/confirm", [
            'confirmation_token' => $token,
        ])->assertOk()
            ->assertJsonPath('replayed', true)
            ->assertJsonPath('result.order_id', $orderId);
        self::assertSame(1, DB::table('orders')->where('tenant_id', $tenant->id)->count());
        self::assertSame(8, (int) DB::table('products')->where('tenant_id', $tenant->id)->where('id', $product->id)->value('stock_quantity'));
    }

    public function test_order_confirmation_requires_a_new_quote_when_price_or_availability_changes(): void
    {
        [$owner, $tenant] = $this->ownerTenant();
        $product = Product::factory()
            ->forTenant($tenant)
            ->withStock(10)
            ->create(['price' => 100, 'is_active' => true, 'is_visible' => true]);
        Sanctum::actingAs($owner);

        $prepared = $this->withHeader('Idempotency-Key', 'order-quote-drift-001')
            ->postJson('/api/v1/tenant/agent/tools/prepare', [
                'tool' => 'commerce.order.create',
                'payload' => [
                    'customer_name' => 'Cliente cotizado',
                    'customer_phone' => '9999-4444',
                    'delivery_type' => 'pickup',
                    'payment_method' => 'cash',
                    'items' => [['product_id' => $product->id, 'quantity' => 2]],
                ],
            ])->assertCreated()
            ->assertJsonPath('confirmation.preview.quote.total', 200);

        $id = (string) $prepared->json('confirmation.id');
        $token = (string) $prepared->json('confirmation.confirmation_token');
        DB::table('products')->where('tenant_id', $tenant->id)->where('id', $product->id)->update(['price' => 125]);

        $this->postJson("/api/v1/tenant/agent/tools/{$id}/confirm", [
            'confirmation_token' => $token,
        ])->assertConflict()->assertJsonPath('code', 'quote_changed');
        self::assertSame(0, DB::table('orders')->where('tenant_id', $tenant->id)->count());
        self::assertSame(10, (int) DB::table('products')->where('tenant_id', $tenant->id)->where('id', $product->id)->value('stock_quantity'));

        DB::table('products')->where('tenant_id', $tenant->id)->where('id', $product->id)->update(['price' => 100]);
        $preparedUnavailable = $this->withHeader('Idempotency-Key', 'order-quote-drift-002')
            ->postJson('/api/v1/tenant/agent/tools/prepare', [
                'tool' => 'commerce.order.create',
                'payload' => [
                    'customer_name' => 'Cliente disponibilidad',
                    'customer_phone' => '9999-5555',
                    'delivery_type' => 'pickup',
                    'payment_method' => 'cash',
                    'items' => [['product_id' => $product->id, 'quantity' => 1]],
                ],
            ])->assertCreated();
        $unavailableId = (string) $preparedUnavailable->json('confirmation.id');
        $unavailableToken = (string) $preparedUnavailable->json('confirmation.confirmation_token');
        DB::table('products')->where('tenant_id', $tenant->id)->where('id', $product->id)->update(['is_visible' => false]);

        $this->postJson("/api/v1/tenant/agent/tools/{$unavailableId}/confirm", [
            'confirmation_token' => $unavailableToken,
        ])->assertConflict()->assertJsonPath('code', 'quote_changed');
        self::assertSame(0, DB::table('orders')->where('tenant_id', $tenant->id)->count());
    }

    public function test_confirmed_delivery_is_validated_and_persisted(): void
    {
        [$owner, $tenant] = $this->ownerTenant();
        $product = Product::factory()
            ->forTenant($tenant)
            ->withStock(5)
            ->create(['price' => 75, 'is_active' => true, 'is_visible' => true]);
        Sanctum::actingAs($owner);

        $this->withHeader('Idempotency-Key', 'delivery-missing-address')
            ->postJson('/api/v1/tenant/agent/tools/prepare', [
                'tool' => 'commerce.order.create',
                'payload' => [
                    'customer_name' => 'Cliente entrega',
                    'customer_phone' => '9999-6666',
                    'delivery_type' => 'delivery',
                    'payment_method' => 'cash',
                    'items' => [['product_id' => $product->id, 'quantity' => 1]],
                ],
            ])->assertUnprocessable();

        $prepared = $this->withHeader('Idempotency-Key', 'delivery-confirmed')
            ->postJson('/api/v1/tenant/agent/tools/prepare', [
                'tool' => 'commerce.order.create',
                'payload' => [
                    'customer_name' => 'Cliente entrega',
                    'customer_phone' => '9999-6666',
                    'customer_address' => 'Barrio Centro, Tegucigalpa',
                    'delivery_type' => 'delivery',
                    'payment_method' => 'cash',
                    'items' => [['product_id' => $product->id, 'quantity' => 1]],
                ],
            ])->assertCreated()
            ->assertJsonPath('confirmation.preview.quote.delivery_type', 'delivery')
            ->assertJsonPath('confirmation.preview.quote.delivery_fee', 0);

        $id = (string) $prepared->json('confirmation.id');
        $this->postJson("/api/v1/tenant/agent/tools/{$id}/confirm", [
            'confirmation_token' => (string) $prepared->json('confirmation.confirmation_token'),
        ])->assertOk();

        $this->assertDatabaseHas('orders', [
            'tenant_id' => $tenant->id,
            'delivery_type' => 'delivery',
            'delivery_address' => 'Barrio Centro, Tegucigalpa',
            'delivery_fee' => 0,
        ]);
    }

    public function test_retention_command_prunes_only_old_terminal_or_expired_confirmations(): void
    {
        [$owner, $tenant] = $this->ownerTenant();
        $oldId = (string) Str::uuid();
        $recentId = (string) Str::uuid();

        foreach ([
            [$oldId, 'completed', now()->subDays(31)],
            [$recentId, 'pending', now()],
        ] as [$id, $status, $createdAt]) {
            DB::table('ai_tool_confirmations')->insert([
                'id' => $id,
                'tenant_id' => $tenant->id,
                'actor_id' => $owner->id,
                'tool' => 'tenant_lead.prepare',
                'payload' => 'encrypted-payload',
                'payload_hash' => hash('sha256', "payload-{$id}"),
                'confirmation_token' => 'encrypted-token',
                'confirmation_token_hash' => hash('sha256', "token-{$id}"),
                'idempotency_key' => "retention-{$id}",
                'status' => $status,
                'expires_at' => $createdAt->copy()->addMinutes(10),
                'executed_at' => $status === 'completed' ? $createdAt : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        self::assertSame(0, Artisan::call('ai:prune-tool-confirmations', [
            '--days' => 30,
            '--limit' => 100,
        ]));

        $this->assertDatabaseMissing('ai_tool_confirmations', ['id' => $oldId]);
        $this->assertDatabaseHas('ai_tool_confirmations', ['id' => $recentId]);
    }

    /** @return array{User, Tenant} */
    private function ownerTenant(): array
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create([
            'is_active' => true,
            'city_id' => City::factory()->create()->id,
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        config([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => array_values(array_unique([
                ...(array) config('ai.private.tenant_allowlist', []),
                (string) $tenant->id,
            ])),
            'ai.tool_confirmations.enabled' => true,
            'ai.tool_confirmations.ttl_seconds' => 600,
        ]);

        return [$owner, $tenant];
    }
}
