<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class DiscoveryAuthorityRatchetTest extends TestCase
{
    public function test_discovery_slice_cannot_mutate_canonical_business_or_economic_tables(): void
    {
        $root = dirname(__DIR__, 2);
        $violations = [];

        foreach ([
            $root.'/app/Application/Discovery',
            $root.'/app/Infrastructure/Discovery',
        ] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());
                if ($this->hasForbiddenCanonicalMutation($source)) {
                    $violations[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                }
            }
        }

        self::assertSame([], $violations, 'Discovery must stay projection-only and must not write canonical business tables.');
    }

    #[DataProvider('forbiddenMutationSamples')]
    public function test_ratchet_detects_forbidden_mutation_samples(string $source): void
    {
        self::assertTrue($this->hasForbiddenCanonicalMutation($source));
    }

    public function test_ratchet_allows_projection_writes(): void
    {
        self::assertFalse($this->hasForbiddenCanonicalMutation(
            "<?php DB::table('discovery_published_offers')->update(['is_current' => 1]);"
        ));
    }

    private function hasForbiddenCanonicalMutation(string $source): bool
    {
        return preg_match('/\b(?:Product|Service|Tenant|Order|Appointment|Quote)\s*::\s*(?:create|forceCreate|updateOrCreate|upsert|insert)\s*\(/', $source) === 1
            || preg_match('/\bDB\s*::\s*table\s*\(\s*[\'"](products|services|tenants|orders|appointments|quotes)[\'"]\s*\)[^;]{0,3000}?->\s*(?:insert|update|delete|upsert|increment|decrement)\s*\(/i', $source) === 1
            || preg_match('/->\s*(?:products|services|orders|appointments|quotes)\s*\(\s*\)\s*->\s*(?:create|save|update|delete|upsert)\s*\(/i', $source) === 1;
    }

    /** @return array<string, array{string}> */
    public static function forbiddenMutationSamples(): array
    {
        return [
            'canonical product upsert' => ["<?php Product::upsert([['name' => 'x']], ['id']);"],
            'orders table update' => ["<?php DB::table('orders')->update(['status' => 'paid']);"],
            'relationship appointment create' => ["<?php \$tenant->appointments()->create(['status' => 'draft']);"],
        ];
    }
}
