<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Application\BusinessAi\Contracts\BusinessKnowledgeIndexerInterface;
use App\Jobs\BusinessAi\ReindexTenantBusinessKnowledgeJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(DatabaseTransactions::class);

function businessRagOwnerFor(Tenant $tenant): User
{
    $user = User::factory()->tenantOwner()->create([
        'tenant_id' => $tenant->id,
    ]);

    $tenant->forceFill(['user_id' => $user->id])->save();

    return $user;
}

test('tenant owner can reindex private business knowledge and chat with tenant scoped sources', function () {
    config()->set('services.openai.api_key', 'test-key');
    config()->set('ai-rag.private.llm_enabled', false);
    config()->set('ai-rag.private.vector_store.driver', 'sql');
    config()->set('ai-rag.private.qdrant.enabled', false);
    Queue::fake();

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [
                ['index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
            ],
        ], 200),
    ]);

    $tenant = Tenant::factory()->create(['name' => 'Ferreteria Vito']);
    $otherTenant = Tenant::factory()->create(['name' => 'Competidor Oculto']);
    $owner = businessRagOwnerFor($tenant);

    Product::factory()->forTenant($tenant)->create([
        'name' => 'Taladro premium',
        'description' => 'Ideal para mantenimiento y construccion liviana',
        'price' => 1250,
    ]);
    Product::factory()->forTenant($otherTenant)->create([
        'name' => 'Producto secreto de otro tenant',
        'description' => 'No debe aparecer nunca',
        'price' => 99,
    ]);

    $this->actingAs($owner)
        ->withHeader('X-Tenant-ID', (string) $tenant->getKey())
        ->postJson('/api/v1/ai/business/knowledge/reindex')
        ->assertStatus(202)
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('scope', 'tenant_private');

    Queue::assertPushed(
        ReindexTenantBusinessKnowledgeJob::class,
        fn (ReindexTenantBusinessKnowledgeJob $job): bool =>
            $job->tenantId === (string) $tenant->getKey()
            && $job->requestedBy === (string) $owner->getKey()
    );

    app(BusinessKnowledgeIndexerInterface::class)->indexTenant($tenant);

    expect(DB::table('tenant_knowledge_embeddings')->where('tenant_id', $tenant->id)->count())->toBeGreaterThan(0);

    $response = $this->actingAs($owner)
        ->withHeader('X-Tenant-ID', (string) $tenant->getKey())
        ->postJson('/api/v1/ai/business/chat', [
            'query' => 'Que producto tengo para mantenimiento?',
            'limit' => 4,
        ])
        ->assertOk()
        ->assertJsonPath('meta.scope', 'tenant_private');

    $payload = $response->json();
    expect(data_get($payload, 'data.answer'))->toContain('Taladro premium');
    expect(data_get($payload, 'data.answer'))->not->toContain('Producto secreto');
    expect(DB::table('business_ai_rag_audits')->where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('business rag rejects users without tenant ai permission', function () {
    $tenant = Tenant::factory()->create();
    $intruder = User::factory()->create(['tenant_id' => null]);

    $this->actingAs($intruder)
        ->withHeader('X-Tenant-ID', (string) $tenant->getKey())
        ->postJson('/api/v1/ai/business/chat', ['query' => 'ventas de hoy'])
        ->assertForbidden();
});

test('business rag requires explicit tenant context for multi tenant safety', function () {
    $tenant = Tenant::factory()->create();
    $owner = businessRagOwnerFor($tenant);

    $this->actingAs($owner)
        ->postJson('/api/v1/ai/business/chat', ['query' => 'ventas de hoy'])
        ->assertStatus(422);
});

test('private business rag uses private qdrant collection and keeps tenant filter', function () {
    config()->set('services.openai.api_key', 'test-key');
    config()->set('ai-rag.private.llm_enabled', false);
    config()->set('ai-rag.private.vector_store.driver', 'qdrant');
    config()->set('ai-rag.private.qdrant.enabled', true);
    config()->set('ai-rag.private.qdrant.url', 'https://qdrant-private.example.test');
    config()->set('ai-rag.private.qdrant.wait_for_upsert', false);
    config()->set('ai-rag.scopes.tenant_private.collection', 'tenant_private_documents');
    config()->set('ai-rag.embedding_dimension', 3);

    $tenant = Tenant::factory()->create(['name' => 'Ferreteria Vectorial']);
    $otherTenant = Tenant::factory()->create(['name' => 'Competidor Vectorial']);
    $owner = businessRagOwnerFor($tenant);

    $product = Product::factory()->forTenant($tenant)->create([
        'name' => 'Qdrant private drill',
        'description' => 'Taladro indexado en coleccion privada',
        'price' => 1400,
    ]);
    $otherProduct = Product::factory()->forTenant($otherTenant)->create([
        'name' => 'Producto secreto de otro tenant',
        'description' => 'No debe aparecer nunca',
        'price' => 99,
    ]);

    Http::fake([
        'https://api.openai.com/v1/embeddings' => Http::response([
            'data' => [
                ['index' => 0, 'embedding' => [0.2, 0.3, 0.4]],
                ['index' => 1, 'embedding' => [0.5, 0.6, 0.7]],
            ],
        ], 200),
        'https://qdrant-private.example.test/collections/tenant_private_documents/points?wait=false' => Http::response(['status' => 'ok'], 200),
        'https://qdrant-private.example.test/collections/tenant_private_documents/points/search' => Http::response([
            'result' => [
                [
                    'id' => 'cross-tenant-source',
                    'score' => 0.99,
                    'payload' => [
                        'tenant_id' => (string) $otherTenant->getKey(),
                        'rag_scope' => 'tenant_private',
                        'visibility' => 'private',
                        'source_type' => 'product',
                        'source_id' => (string) $otherProduct->getKey(),
                        'title' => 'Producto secreto de otro tenant',
                        'excerpt' => 'No debe aparecer nunca.',
                    ],
                ],
                [
                    'id' => 'tenant-source',
                    'score' => 0.91,
                    'payload' => [
                        'tenant_id' => (string) $tenant->getKey(),
                        'rag_scope' => 'tenant_private',
                        'visibility' => 'private',
                        'source_type' => 'product',
                        'source_id' => (string) $product->getKey(),
                        'title' => 'Qdrant private drill',
                        'excerpt' => 'Taladro indexado en coleccion privada.',
                    ],
                ],
            ],
        ], 200),
    ]);

    app(BusinessKnowledgeIndexerInterface::class)->indexTenant($tenant);

    $response = $this->actingAs($owner)
        ->withHeader('X-Tenant-ID', (string) $tenant->getKey())
        ->postJson('/api/v1/ai/business/chat', [
            'query' => 'Que taladro privado tengo?',
            'limit' => 4,
        ])
        ->assertOk()
        ->assertJsonPath('data.sources.0.retrieval_mode', 'qdrant');

    expect(data_get($response->json(), 'data.answer'))->toContain('Qdrant private drill');
    expect(data_get($response->json(), 'data.answer'))->not->toContain('Producto secreto');

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request): bool =>
        $request->method() === 'PUT'
        && str_contains($request->url(), '/collections/tenant_private_documents/points')
        && str_contains($request->url(), 'wait=false')
        && count(data_get($request->data(), 'points', [])) >= 2
        && data_get($request->data(), 'points.0.payload.tenant_id') === (string) $tenant->getKey()
        && data_get($request->data(), 'points.0.payload.rag_scope') === 'tenant_private'
        && data_get($request->data(), 'points.0.payload.visibility') === 'private'
    );

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request): bool =>
        $request->method() === 'POST'
        && str_contains($request->url(), '/collections/tenant_private_documents/points/search')
        && data_get($request->data(), 'filter.must.0.key') === 'tenant_id'
        && data_get($request->data(), 'filter.must.0.match.value') === (string) $tenant->getKey()
    );
});

test('private rag redacts secrets before embeddings payloads answers and audits', function () {
    config()->set('services.openai.api_key', 'test-key');
    config()->set('ai-rag.private.llm_enabled', false);
    config()->set('ai-rag.private.vector_store.driver', 'sql');
    config()->set('ai-rag.private.qdrant.enabled', false);

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [
                ['index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
            ],
        ], 200),
    ]);

    $tenant = Tenant::factory()->create([
        'name' => 'Negocio Seguro',
        'description' => 'Contactar a owner@example.com password=super-secret direccion: Colonia Centro bloque 8',
    ]);
    $owner = businessRagOwnerFor($tenant);

    app(BusinessKnowledgeIndexerInterface::class)->indexTenant($tenant);

    $embeddingInput = (string) DB::table('tenant_knowledge_embeddings')
        ->where('tenant_id', $tenant->id)
        ->where('source_type', 'tenant_profile')
        ->value('embedding_input');

    expect($embeddingInput)->toContain('[email]')
        ->and($embeddingInput)->toContain('[secret]')
        ->and($embeddingInput)->toContain('[address]')
        ->and($embeddingInput)->not->toContain('owner@example.com')
        ->and($embeddingInput)->not->toContain('super-secret');

    $response = $this->actingAs($owner)
        ->withHeader('X-Tenant-ID', (string) $tenant->getKey())
        ->postJson('/api/v1/ai/business/chat', [
            'query' => 'mi email es cliente@example.com y access_token=abc123, que sabes?',
        ])
        ->assertOk();

    expect(data_get($response->json(), 'data.answer'))->not->toContain('cliente@example.com')
        ->and(data_get($response->json(), 'data.answer'))->not->toContain('abc123');

    $audit = DB::table('business_ai_rag_audits')->where('tenant_id', $tenant->id)->first();

    expect((string) $audit->query_excerpt)->toContain('[email]')
        ->and((string) $audit->query_excerpt)->toContain('[secret]')
        ->and((string) $audit->query_excerpt)->not->toContain('cliente@example.com')
        ->and((string) $audit->query_excerpt)->not->toContain('abc123');
});
