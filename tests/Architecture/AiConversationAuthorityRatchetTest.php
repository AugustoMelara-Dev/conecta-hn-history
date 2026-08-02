<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class AiConversationAuthorityRatchetTest extends TestCase
{
    public function test_only_canonical_ai_conversation_owners_can_mutate_conversation_tables(): void
    {
        $root = dirname(__DIR__, 2);
        $directories = [$root.'/app'];
        $allowed = [
            'app/Application/Ai/Conversation/AiConversationRetention.php',
            'app/Application/Ai/Conversation/AiFoundationBackfill.php',
            'app/Application/Ai/Handoff/AiHandoffService.php',
            'app/Infrastructure/AI/Conversation/EloquentConversationStore.php',
        ];
        $violations = [];

        foreach ($directories as $directory) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                if (in_array($relative, $allowed, true)) {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());
                if ($this->writesConversationAuthority($source)) {
                    $violations[] = $relative;
                }
            }
        }

        self::assertSame([], $violations, 'Conversation authority must stay in the canonical store, retention, handoff, and backfill owners only.');
    }

    private function writesConversationAuthority(string $source): bool
    {
        return preg_match('/DB\s*::\s*table\s*\(\s*[\'"](ai_conversations|ai_conversation_participants|ai_messages|ai_message_evidence|ai_message_failures|ai_handoffs)[\'"]\s*\)/i', $source) === 1
            && preg_match('/->\s*(?:insert|insertOrIgnore|update|upsert|delete|truncate)\s*\(/i', $source) === 1
            || preg_match('/(?:insert\s+into|update\s+|delete\s+from)\s+(?:`|\")?(ai_conversations|ai_conversation_participants|ai_messages|ai_message_evidence|ai_message_failures|ai_handoffs)/i', $source) === 1;
    }
}
