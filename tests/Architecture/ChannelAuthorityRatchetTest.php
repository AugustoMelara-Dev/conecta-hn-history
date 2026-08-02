<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ChannelAuthorityRatchetTest extends TestCase
{
    public function test_channel_adapters_cannot_write_canonical_ai_conversation_tables(): void
    {
        $root = dirname(__DIR__, 2);
        $directories = [
            $root.'/app/Application/Channels',
            $root.'/app/Infrastructure/Channels',
        ];
        $violations = [];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());
                if ($this->writesCanonicalConversationTables($source)) {
                    $violations[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                }
            }
        }

        self::assertSame([], $violations, 'Program 07 adapters must not create a second conversation authority.');
    }

    public function test_channel_migration_declares_pgsql_rls_guards_for_every_tenant_table(): void
    {
        $migration = (string) file_get_contents(
            dirname(__DIR__, 2).'/database/migrations/2026_08_01_000002_expand_program07_channels.php',
        );

        foreach ([
            'channel_connections',
            'channel_capability_snapshots',
            'channel_provider_health',
            'channel_consents',
            'channel_identities',
            'channel_delivery_outbox',
            'channel_delivery_attempts',
            'channel_webhook_claims',
        ] as $table) {
            self::assertStringContainsString($table, $migration);
        }

        self::assertStringContainsString('ENABLE ROW LEVEL SECURITY', $migration);
        self::assertStringContainsString('FORCE ROW LEVEL SECURITY', $migration);
        self::assertStringContainsString('CREATE POLICY', $migration);
    }

    private function writesCanonicalConversationTables(string $source): bool
    {
        return preg_match('/DB\s*::\s*table\s*\(\s*[\'"](ai_conversations|ai_messages|ai_message_failures)[\'"]\s*\)/i', $source) === 1
            || preg_match('/insert\s+into\s+(ai_conversations|ai_messages|ai_message_failures)/i', $source) === 1;
    }
}
