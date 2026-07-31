<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Conversation\AcceptConversationMessage;
use App\Application\Ai\Conversation\ConversationStore;
use App\Application\Ai\Security\AiContentCipher;
use App\Application\Ai\Security\EncryptedAiContent;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class ConversationEncryptionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(TenantContext::class)->reset();

        parent::tearDown();
    }

    public function test_message_content_uses_the_cipher_interface_and_exact_associated_data_tuple(): void
    {
        $tenant = Tenant::factory()->create();
        $this->supportMarket();
        app(TenantContext::class)->setTenant((string) $tenant->id);
        $store = app(ConversationStore::class);
        $claim = $store->claim($this->command(
            (string) $tenant->id,
            'Associated-data-sensitive plaintext.',
            'encryption-001',
        ));
        $stored = DB::table('ai_messages')->where('id', $claim->requestMessage->id)->first();
        self::assertNotNull($stored);
        $encrypted = new EncryptedAiContent(
            ciphertext: (string) $stored->content_ciphertext,
            contentHash: (string) $stored->content_hash,
            keyVersion: (string) $stored->encryption_key_version,
        );
        $cipher = app(AiContentCipher::class);

        self::assertSame(
            'Associated-data-sensitive plaintext.',
            $cipher->decrypt(
                (string) $tenant->id,
                $claim->requestMessage->id,
                'conversation_message',
                $encrypted,
            ),
        );

        foreach ([
            [(string) Str::uuid(), $claim->requestMessage->id, 'conversation_message'],
            [(string) $tenant->id, (string) Str::uuid(), 'conversation_message'],
            [(string) $tenant->id, $claim->requestMessage->id, 'prompt_template'],
        ] as [$tenantId, $recordId, $contentClass]) {
            try {
                $cipher->decrypt($tenantId, $recordId, $contentClass, $encrypted);
                self::fail('Wrong associated data must fail authentication.');
            } catch (RuntimeException $exception) {
                self::assertSame(
                    'AI content associated data validation failed.',
                    $exception->getMessage(),
                );
            }
        }
    }

    public function test_plaintext_and_external_thread_reference_never_enter_conversation_tables_or_logs(): void
    {
        $tenant = Tenant::factory()->create();
        $this->supportMarket();
        app(TenantContext::class)->setTenant((string) $tenant->id);
        $messages = [];
        Event::listen(MessageLogged::class, static function (MessageLogged $event) use (&$messages): void {
            $messages[] = $event->message.' '.json_encode($event->context, JSON_THROW_ON_ERROR);
        });
        $store = app(ConversationStore::class);
        $requestPlaintext = 'PRIVATE-REQUEST-7fd7bb22';
        $threadPlaintext = 'PRIVATE-THREAD-7fd7bb22';
        $claim = $store->claim(new AcceptConversationMessage(
            tenantId: (string) $tenant->id,
            visibility: 'tenant_private',
            originType: 'web',
            externalThreadReference: $threadPlaintext,
            actorId: 'owner-001',
            locale: 'en-CA',
            marketCountryCode: 'CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            content: $requestPlaintext,
            contentType: 'text',
            idempotencyKey: 'encryption-plaintext-001',
        ));
        $store->failAssistantMessage(
            (string) $tenant->id,
            $claim->conversationId,
            $claim->requestMessage->id,
            'ai_response_invalid',
            false,
        );

        foreach (['ai_conversations', 'ai_messages', 'ai_message_evidence', 'ai_message_failures'] as $table) {
            $serialized = json_encode(
                DB::table($table)->get()->map(static fn (object $row): array => (array) $row)->all(),
                JSON_THROW_ON_ERROR,
            );
            self::assertStringNotContainsString($requestPlaintext, $serialized, $table);
            self::assertStringNotContainsString($threadPlaintext, $serialized, $table);
        }
        $serializedLogs = implode("\n", $messages);
        self::assertStringNotContainsString($requestPlaintext, $serializedLogs);
        self::assertStringNotContainsString($threadPlaintext, $serializedLogs);
        self::assertNull(DB::table('ai_message_failures')->value('failure_detail'));
    }

    private function command(string $tenantId, string $content, string $idempotencyKey): AcceptConversationMessage
    {
        return new AcceptConversationMessage(
            tenantId: $tenantId,
            visibility: 'tenant_private',
            originType: 'web',
            externalThreadReference: null,
            actorId: 'owner-001',
            locale: 'en-CA',
            marketCountryCode: 'CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            content: $content,
            contentType: 'text',
            idempotencyKey: $idempotencyKey,
        );
    }

    private function supportMarket(): void
    {
        $now = now();
        DB::table('countries')->insert([
            'code' => 'CA', 'name' => 'Canada', 'native_name' => 'Canada',
            'calling_code' => '+1', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('currencies')->insert([
            'code' => 'CAD', 'name' => 'Canadian dollar', 'symbol' => '$',
            'minor_unit' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('locales')->insert([
            'code' => 'en-CA', 'name' => 'English (Canada)', 'native_name' => 'English (Canada)',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('timezones')->insert([
            'name' => 'America/Toronto', 'label' => 'Toronto', 'utc_offset' => '-05:00',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
