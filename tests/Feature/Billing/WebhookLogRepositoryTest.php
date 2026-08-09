<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Infrastructure\Repositories\Payment\EloquentWebhookLogRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use UnexpectedValueException;

final class WebhookLogRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function record_first_seen_does_not_abandon_a_processing_claim(): void
    {
        $repository = new EloquentWebhookLogRepository;

        self::assertTrue($repository->recordFirstSeen(
            'stripe',
            'evt_first_seen',
            'payment_intent.succeeded',
            ['id' => 'evt_first_seen'],
        ));
        self::assertFalse($repository->recordFirstSeen(
            'stripe',
            'evt_first_seen',
            'payment_intent.succeeded',
            ['id' => 'evt_first_seen'],
        ));
        self::assertTrue($repository->hasBeenProcessed('stripe', 'evt_first_seen'));
    }

    #[Test]
    public function it_claims_once_marks_processed_and_rejects_a_processed_duplicate(): void
    {
        $repository = new EloquentWebhookLogRepository;

        $claim = $repository->claim('stripe', 'evt_claimed', 'payment_intent.succeeded', ['id' => 'evt_claimed']);
        self::assertNotNull($claim);
        self::assertFalse($repository->hasBeenProcessed('stripe', 'evt_claimed'));

        self::assertTrue($repository->markProcessed($claim));

        self::assertTrue($repository->hasBeenProcessed('stripe', 'evt_claimed'));
        self::assertNull($repository->claim('stripe', 'evt_claimed', 'payment_intent.succeeded', ['id' => 'evt_claimed']));
        $this->assertDatabaseHas('webhook_logs', [
            'provider' => 'stripe',
            'external_event_id' => 'evt_claimed',
            'status' => 'processed',
            'attempts' => 1,
        ]);
    }

    #[Test]
    public function it_reclaims_a_failed_event_with_the_same_provider_event_id(): void
    {
        $repository = new EloquentWebhookLogRepository;
        $payload = ['id' => 'evt_retry'];

        $claim = $repository->claim('stripe', 'evt_retry', 'payment_intent.succeeded', $payload);
        self::assertNotNull($claim);
        self::assertTrue($repository->markFailed($claim, new RuntimeException("provider timeout\nsecret=not-persisted Authorization: Bearer bearer-token")));

        $storedError = (string) DB::table('webhook_logs')->where('external_event_id', 'evt_retry')->value('last_error');
        self::assertStringContainsString('secret=[REDACTED]', $storedError);
        self::assertStringContainsString('Authorization=[REDACTED]', $storedError);
        self::assertStringNotContainsString('not-persisted', $storedError);
        self::assertStringNotContainsString('Bearer', $storedError);
        self::assertStringNotContainsString('bearer-token', $storedError);

        self::assertNotNull($repository->claim('stripe', 'evt_retry', 'payment_intent.succeeded', $payload));
        $this->assertDatabaseHas('webhook_logs', [
            'provider' => 'stripe',
            'external_event_id' => 'evt_retry',
            'status' => 'processing',
            'attempts' => 2,
            'last_error' => null,
        ]);
    }

    #[Test]
    public function it_reclaims_an_expired_processing_lease_but_not_an_active_one(): void
    {
        $repository = new EloquentWebhookLogRepository;
        $payload = ['id' => 'evt_stale'];

        self::assertNotNull($repository->claim('stripe', 'evt_stale', 'payment_intent.succeeded', $payload, 60));
        self::assertNull($repository->claim('stripe', 'evt_stale', 'payment_intent.succeeded', $payload, 60));

        DB::table('webhook_logs')->where('provider', 'stripe')->where('external_event_id', 'evt_stale')->update([
            'processing_started_at' => now()->subMinutes(2),
        ]);

        self::assertNotNull($repository->claim('stripe', 'evt_stale', 'payment_intent.succeeded', $payload, 60));
        self::assertSame(2, (int) DB::table('webhook_logs')->where('external_event_id', 'evt_stale')->value('attempts'));
    }

    #[Test]
    public function it_rejects_an_event_id_reused_with_an_incompatible_payload(): void
    {
        $repository = new EloquentWebhookLogRepository;

        self::assertNotNull($repository->claim('stripe', 'evt_conflict', 'payment_intent.succeeded', ['id' => 'evt_conflict', 'amount' => 49900]));

        $this->expectException(UnexpectedValueException::class);
        $repository->claim('stripe', 'evt_conflict', 'payment_intent.succeeded', ['id' => 'evt_conflict', 'amount' => 1]);
    }

    #[Test]
    public function an_expired_claim_cannot_fence_a_newer_claim(): void
    {
        $repository = new EloquentWebhookLogRepository;
        $payload = ['id' => 'evt_fenced'];

        $firstClaim = $repository->claim('stripe', 'evt_fenced', 'payment_intent.succeeded', $payload, 60);
        self::assertNotNull($firstClaim);

        DB::table('webhook_logs')->where('external_event_id', 'evt_fenced')->update([
            'processing_started_at' => now()->subMinutes(2),
        ]);

        $secondClaim = $repository->claim('stripe', 'evt_fenced', 'payment_intent.succeeded', $payload, 60);
        self::assertNotNull($secondClaim);
        self::assertNotSame($firstClaim->processingToken, $secondClaim->processingToken);

        self::assertFalse($repository->markProcessed($firstClaim));
        self::assertFalse($repository->markFailed($firstClaim, new RuntimeException('old worker failed')));
        self::assertSame('processing', DB::table('webhook_logs')->where('external_event_id', 'evt_fenced')->value('status'));

        self::assertTrue($repository->markProcessed($secondClaim));
        self::assertSame('processed', DB::table('webhook_logs')->where('external_event_id', 'evt_fenced')->value('status'));
    }

    #[Test]
    public function a_legacy_processed_row_without_a_hash_is_a_safe_duplicate(): void
    {
        DB::table('webhook_logs')->insert([
            'provider' => 'stripe',
            'external_event_id' => 'evt_legacy',
            'event_type' => 'payment_intent.succeeded',
            'payload' => json_encode(['legacy' => true], JSON_THROW_ON_ERROR),
            'status' => 'processed',
            'attempts' => 1,
            'processing_started_at' => now(),
            'processed_at' => now(),
            'payload_hash' => null,
            'created_at' => now(),
        ]);

        $repository = new EloquentWebhookLogRepository;

        self::assertNull($repository->claim('stripe', 'evt_legacy', 'payment_intent.succeeded', ['different' => 'representation']));
        self::assertTrue($repository->hasBeenProcessed('stripe', 'evt_legacy'));
    }

    #[Test]
    public function it_hashes_the_full_payload_but_does_not_retain_raw_customer_or_secret_data(): void
    {
        $repository = new EloquentWebhookLogRepository;
        $payload = [
            'id' => 'evt_private',
            'event' => 'payment.success',
            'data' => ['object' => [
                'id' => 'pi_private',
                'receipt_email' => 'customer@example.test',
                'client_secret' => 'secret-value',
                'metadata' => ['tenant_id' => 'tenant-safe', 'order_id' => 42],
            ]],
        ];

        self::assertNotNull($repository->claim('stripe', 'evt_private', 'payment.success', $payload));
        $stored = (string) DB::table('webhook_logs')->where('external_event_id', 'evt_private')->value('payload');

        self::assertStringContainsString('raw_payload_retained', $stored);
        self::assertStringNotContainsString('customer@example.test', $stored);
        self::assertStringNotContainsString('secret-value', $stored);
    }
}
