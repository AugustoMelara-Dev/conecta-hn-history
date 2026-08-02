<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ChannelAuthorityRatchetTest extends TestCase
{
    public function test_conversation_bridge_uses_the_existing_channel_and_conversation_authorities(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string) file_get_contents($root.'/app/Application/Channels/ConversationChannelBridge.php');

        self::assertStringContainsString('ChannelConnectionService', $source);
        self::assertStringContainsString('ChannelIdentityResolver', $source);
        self::assertStringContainsString('ChannelConsentService', $source);
        self::assertStringContainsString('ConversationStore', $source);
        self::assertStringContainsString('claimWebhook', $source);
        self::assertStringContainsString('->claim(', $source);
        self::assertStringNotContainsString('new EloquentConversationStore', $source);
    }

    public function test_channel_links_do_not_persist_message_content(): void
    {
        $migration = (string) file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_02_210200_expand_dovra_conversation_channels.php');

        self::assertStringContainsString('normalized_identity_hash', $migration);
        self::assertStringContainsString('payload_hash', $migration);
        self::assertStringNotContainsString('$table->text(\'content\')', $migration);
    }
}
