<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class DirectoryGuideChatTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_SUBJECT_COOKIE = 'dovra_ai_subject';

    protected function setUp(): void
    {
        parent::setUp();

        $platform = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($platform);
        config()->set('ai.public.billing_tenant_id', (string) $platform->id);
    }

    public function test_public_directory_guide_answers_from_public_products_with_budget(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Cafe Norte',
            'slug' => 'cafe-norte',
            'is_active' => true,
        ]);

        Product::factory()->forTenant($tenant)->create([
            'name' => 'Cafe frio',
            'slug' => 'cafe-frio',
            'price' => 95,
            'is_active' => true,
            'is_visible' => true,
        ]);

        Product::factory()->forTenant($tenant)->create([
            'name' => 'Canasta premium',
            'slug' => 'canasta-premium',
            'price' => 350,
            'is_active' => true,
            'is_visible' => true,
        ]);

        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'que puedo comprar con 150 lempiras',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'conversation_id',
                'request_message_id',
                'response_message_id',
                'status',
                'code',
            ])
            ->assertJsonPath('source', 'public_directory')
            ->assertJsonPath('fallback', true)
            ->assertJsonFragment(['name' => 'Cafe frio'])
            ->assertJsonMissing(['name' => 'Canasta premium']);
    }

    public function test_public_directory_guide_returns_services_and_excludes_unavailable_stock(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Bienestar Centro',
            'slug' => 'bienestar-centro',
            'is_active' => true,
            'subscription_status' => 'active',
        ]);

        $service = Service::factory()->for($tenant)->create([
            'name' => 'Masaje breve',
            'description' => 'Servicio publico',
            'price' => 120,
            'is_active' => true,
        ]);

        Product::factory()->forTenant($tenant)->outOfStock()->create([
            'name' => 'Aceite agotado',
            'price' => 80,
            'is_active' => true,
            'is_visible' => true,
            'allow_backorders' => false,
        ]);

        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'masaje con 150 lempiras',
        ]);

        $response
            ->assertOk()
            ->assertJsonFragment([
                'evidence_id' => 'service:'.$service->id,
                'name' => 'Masaje breve',
                'availability' => 'available',
            ])
            ->assertJsonMissing(['name' => 'Aceite agotado']);

        self::assertStringNotContainsString('stock_quantity', $response->getContent());
        self::assertStringNotContainsString('tenant_id', $response->getContent());
    }

    public function test_inferred_budget_over_the_public_limit_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'comprar con '.str_repeat('9', 400).' lempiras',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('message');
    }

    public function test_public_directory_guide_never_returns_private_sales_data(): void
    {
        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'cuanto vendio Cafe Norte hoy',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('source', 'public_directory')
            ->assertJsonMissing(['orders'])
            ->assertJsonMissing(['total_sales']);
    }

    public function test_active_tenant_without_active_subscription_is_excluded(): void
    {
        if (! Schema::hasColumn('products', 'status')) {
            Schema::table('products', static function (Blueprint $table): void {
                $table->string('status')->nullable();
            });
        }

        $active = Tenant::factory()->create([
            'name' => 'Suscripcion Activa',
            'slug' => 'suscripcion-activa',
            'is_active' => true,
            'subscription_status' => 'active',
        ]);
        $cancelled = Tenant::factory()->create([
            'name' => 'Suscripcion Cancelada',
            'slug' => 'suscripcion-cancelada',
            'is_active' => true,
            'subscription_status' => 'canceled',
        ]);

        Product::factory()->forTenant($active)->create([
            'name' => 'Producto publicado',
            'price' => 20,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
        ]);
        Product::factory()->forTenant($cancelled)->create([
            'name' => 'Producto sin suscripcion',
            'price' => 10,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
        ]);
        Product::factory()->forTenant($active)->create([
            'name' => 'Producto sin publicar',
            'price' => 15,
            'status' => null,
            'is_active' => true,
            'is_visible' => true,
        ]);

        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'producto con 50 lempiras',
        ]);

        $response
            ->assertOk()
            ->assertJsonFragment(['name' => 'Producto publicado'])
            ->assertJsonMissing(['name' => 'Producto sin suscripcion'])
            ->assertJsonMissing(['name' => 'Producto sin publicar']);
    }

    public function test_public_directory_guide_excludes_hidden_inactive_and_inactive_tenant_products(): void
    {
        $activeTenant = Tenant::factory()->create([
            'name' => 'Mercado Claro',
            'slug' => 'mercado-claro',
            'is_active' => true,
        ]);

        $inactiveTenant = Tenant::factory()->inactive()->create([
            'name' => 'Mercado Cerrado',
            'slug' => 'mercado-cerrado',
            'is_active' => false,
        ]);

        Product::factory()->forTenant($activeTenant)->create([
            'name' => 'Pan visible',
            'slug' => 'pan-visible',
            'price' => 45,
            'is_active' => true,
            'is_visible' => true,
        ]);

        Product::factory()->forTenant($activeTenant)->create([
            'name' => 'Pan oculto',
            'slug' => 'pan-oculto',
            'price' => 40,
            'is_active' => true,
            'is_visible' => false,
        ]);

        Product::factory()->forTenant($activeTenant)->create([
            'name' => 'Pan pausado',
            'slug' => 'pan-pausado',
            'price' => 35,
            'is_active' => false,
            'is_visible' => true,
        ]);

        Product::factory()->forTenant($inactiveTenant)->create([
            'name' => 'Pan privado',
            'slug' => 'pan-privado',
            'price' => 30,
            'is_active' => true,
            'is_visible' => true,
        ]);

        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'que puedo comprar con 50 lempiras',
        ]);

        $response
            ->assertOk()
            ->assertJsonFragment(['name' => 'Pan visible'])
            ->assertJsonMissing(['name' => 'Pan oculto'])
            ->assertJsonMissing(['name' => 'Pan pausado'])
            ->assertJsonMissing(['name' => 'Pan privado']);
    }

    public function test_anonymous_clients_behind_the_same_ip_receive_distinct_server_issued_subjects(): void
    {
        config()->set('session.secure', true);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42']);

        $first = $this->withHeader('Idempotency-Key', 'public-body-subject')
            ->postJson('/api/v1/directory/guide/chat', [
                'message' => 'show published products',
                'budget' => 50,
            ])
            ->assertOk();
        $second = $this->withHeader('Idempotency-Key', 'public-body-subject')
            ->postJson('/api/v1/directory/guide/chat', [
                'message' => 'show published products',
                'budget' => 50,
            ])
            ->assertOk();

        self::assertNotSame($first->json('conversation_id'), $second->json('conversation_id'));
        self::assertNotSame($first->json('request_message_id'), $second->json('request_message_id'));
        $firstCookie = $first->getCookie(self::PUBLIC_SUBJECT_COOKIE);
        $secondCookie = $second->getCookie(self::PUBLIC_SUBJECT_COOKIE);
        self::assertNotNull($firstCookie);
        self::assertNotNull($secondCookie);
        self::assertNotSame($firstCookie->getValue(), $secondCookie->getValue());
        self::assertTrue($firstCookie->isHttpOnly());
        self::assertTrue($firstCookie->isSecure());
        self::assertSame('lax', strtolower((string) $firstCookie->getSameSite()));
        self::assertSame(2, DB::table('ai_conversations')->count());
        $actors = DB::table('ai_conversations')->pluck('actor_id')->all();
        self::assertCount(2, array_unique($actors));
        self::assertNotContains('198.51.100.42', $actors);
    }

    public function test_valid_subject_cookie_replays_and_request_body_cannot_override_it(): void
    {
        $first = $this->withHeader('Idempotency-Key', 'public-cookie-replay')
            ->postJson('/api/v1/directory/guide/chat', [
                'message' => 'show published products',
                'budget' => 50,
                'conversation_subject' => 'attacker-controlled-a',
            ])
            ->assertOk();
        $cookie = $first->getCookie(self::PUBLIC_SUBJECT_COOKIE, false);
        self::assertNotNull($cookie);

        $replay = $this->withCredentials()
            ->withUnencryptedCookie(self::PUBLIC_SUBJECT_COOKIE, $cookie->getValue())
            ->withHeader('Idempotency-Key', 'public-cookie-replay')
            ->postJson('/api/v1/directory/guide/chat', [
                'message' => 'show published products',
                'budget' => 50,
                'conversation_subject' => 'attacker-controlled-b',
            ])
            ->assertOk();

        $replay
            ->assertJsonPath('conversation_id', $first->json('conversation_id'))
            ->assertJsonPath('request_message_id', $first->json('request_message_id'));
        self::assertStringNotContainsString(
            'attacker-controlled',
            json_encode(DB::table('ai_conversations')->get()->toArray(), JSON_THROW_ON_ERROR),
        );
        self::assertStringNotContainsString(
            $cookie->getValue(),
            json_encode(DB::table('ai_conversations')->get()->toArray(), JSON_THROW_ON_ERROR),
        );
    }

    public function test_invalid_subject_cookie_is_rotated_and_cannot_claim_a_stable_identity(): void
    {
        $first = $this->withCredentials()
            ->withUnencryptedCookie(self::PUBLIC_SUBJECT_COOKIE, 'invalid-client-value')
            ->withHeader('Idempotency-Key', 'public-invalid-cookie')
            ->postJson('/api/v1/directory/guide/chat', [
                'message' => 'show published products',
                'budget' => 50,
                'conversation_subject' => 'attacker-controlled',
            ])
            ->assertOk();
        $second = $this->withCredentials()
            ->withUnencryptedCookie(self::PUBLIC_SUBJECT_COOKIE, 'invalid-client-value')
            ->withHeader('Idempotency-Key', 'public-invalid-cookie')
            ->postJson('/api/v1/directory/guide/chat', [
                'message' => 'show published products',
                'budget' => 50,
                'conversation_subject' => 'attacker-controlled',
            ])
            ->assertOk();

        self::assertNotSame($first->json('conversation_id'), $second->json('conversation_id'));
        self::assertNotSame($first->json('request_message_id'), $second->json('request_message_id'));
        $firstCookie = $first->getCookie(self::PUBLIC_SUBJECT_COOKIE);
        $secondCookie = $second->getCookie(self::PUBLIC_SUBJECT_COOKIE);
        self::assertNotNull($firstCookie);
        self::assertNotNull($secondCookie);
        self::assertNotSame('invalid-client-value', $firstCookie->getValue());
        self::assertNotSame($firstCookie->getValue(), $secondCookie->getValue());
    }

    public function test_missing_configured_platform_tenant_returns_safe_service_unavailable(): void
    {
        config()->set('ai.public.billing_tenant_id', (string) Str::uuid());

        $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'show published products',
        ])
            ->assertStatus(503)
            ->assertJsonPath('code', 'ai_configuration_unavailable');
    }
}
