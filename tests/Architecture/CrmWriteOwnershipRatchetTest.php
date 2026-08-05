<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CrmWriteOwnershipRatchetTest extends TestCase
{
    /** @var list<string> */
    private const ALLOWED_WRITE_OWNERS = [
        'app/Application/Crm/CanonicalOpportunityWriter.php',
        'app/Application/Operations/BusinessOperationsBackfill.php',
    ];

    public function test_operational_adapters_cannot_mutate_opportunities_outside_the_canonical_writer(): void
    {
        $root = dirname(__DIR__, 2);
        $violations = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app')) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $relativePath = str_replace('\\', '/', substr($path, strlen($root) + 1));
            if (in_array($relativePath, self::ALLOWED_WRITE_OWNERS, true)) {
                continue;
            }
            if ($this->hasForbiddenMutation((string) file_get_contents($path))) {
                $violations[] = $relativePath;
            }
        }

        self::assertSame(
            [],
            $violations,
            'CRM opportunity mutations must use CanonicalOpportunityWriter.',
        );
    }

    #[DataProvider('forbiddenMutationFixtures')]
    public function test_ratchet_detects_prohibited_direct_mutations(string $source): void
    {
        self::assertTrue($this->hasForbiddenMutation($source));
    }

    public function test_ratchet_allows_opportunity_reads(): void
    {
        self::assertFalse($this->hasForbiddenMutation(
            '<?php App\Models\CrmDeal::query()->whereKey(1)->first();',
        ));
    }

    private function hasForbiddenMutation(string $source): bool
    {
        $models = [
            'CrmDeal',
            'App\Models\CrmDeal',
            '\App\Models\CrmDeal',
        ];
        if (preg_match_all(
            '/use\s+App\\\\Models\\\\CrmDeal(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?\s*;/',
            $source,
            $matches,
            PREG_SET_ORDER,
        ) > 0) {
            foreach ($matches as $match) {
                if (($match[1] ?? '') !== '') {
                    $models[] = $match[1];
                }
            }
        }
        $model = '(?<![A-Za-z0-9_\\\\])(?:'.implode('|', array_map(
            static fn (string $name): string => preg_quote($name, '/'),
            array_unique($models),
        )).')(?![A-Za-z0-9_])';
        $write = '(?:save|saveQuietly|update|updateQuietly|delete|forceDelete|restore|forceFill|increment|decrement)';

        return preg_match('/'.$model.'\s*::\s*(?:create|forceCreate|updateOrCreate|firstOrCreate|upsert|insert)\s*\(/i', $source) === 1
            || preg_match('/'.$model.'\s*::[^;]{0,2000}?->\s*(?:update|delete|forceDelete|restore|increment|decrement)\s*\(/i', $source) === 1
            || preg_match('/\$([A-Za-z_][A-Za-z0-9_]*)\s*=\s*new\s+'.$model.'\b[^;]*;[\s\S]{0,1500}?\$\1\s*->\s*'.$write.'\s*\(/i', $source) === 1
            || preg_match('/'.$model.'\s+\$([A-Za-z_][A-Za-z0-9_]*)\b[\s\S]{0,1500}?\$\1\s*->\s*'.$write.'\s*\(/i', $source) === 1
            || preg_match('/\bDB\s*::\s*table\s*\(\s*[\'"]crm_deals[\'"]\s*\)[^;]{0,3000}?->\s*(?:insert|insertGetId|update|delete|upsert|updateOrInsert|increment|decrement)\s*\(/i', $source) === 1
            || preg_match('/->\s*(?:deals|crmDeals)\s*\(\s*\)\s*->\s*(?:create|save|update|delete|upsert)\s*\(/i', $source) === 1;
    }

    /** @return array<string, array{string}> */
    public static function forbiddenMutationFixtures(): array
    {
        return [
            'static deal create' => ['<?php App\Models\CrmDeal::create([]);'],
            'aliased deal create' => ['<?php use App\Models\CrmDeal as Opportunity; Opportunity::forceCreate([]);'],
            'new deal save' => ['<?php $deal = new App\Models\CrmDeal; $deal->save();'],
            'typed deal delete' => ['<?php function archive(App\Models\CrmDeal $deal) { $deal->delete(); }'],
            'deal query update' => ['<?php App\Models\CrmDeal::query()->whereKey(1)->update([]);'],
            'raw deal upsert' => ["<?php DB::table('crm_deals')->upsert([], ['id']);"],
            'deal relationship create' => ['<?php $pipeline->deals()->create([]);'],
        ];
    }
}
