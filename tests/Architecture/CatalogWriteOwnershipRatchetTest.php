<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CatalogWriteOwnershipRatchetTest extends TestCase
{
    /** @var list<string> */
    private const ALLOWED_WRITE_OWNERS = [
        'app/Application/Products/CanonicalProductWriter.php',
        'app/Application/Operations/BusinessOperationsBackfill.php',
        'app/Services/InventoryManager.php',
        'app/Infrastructure/Repositories/Products/EloquentProductBlurhashWriter.php',
        'app/Infrastructure/Repositories/Products/EloquentProductRatingMetricsWriter.php',
    ];

    public function test_operational_adapters_cannot_mutate_products_outside_documented_owners(): void
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
            if ($this->hasForbiddenProductMutation((string) file_get_contents($path))) {
                $violations[] = $relativePath;
            }
        }

        self::assertSame([], $violations, 'Operational Product mutations must use a documented narrow owner.');
    }

    #[DataProvider('forbiddenMutationFixtures')]
    public function test_ratchet_detects_each_prohibited_direct_product_mutation_fixture(string $fixture): void
    {
        $source = (string) file_get_contents(dirname(__DIR__).'/Fixtures/Architecture/'.$fixture);

        self::assertTrue($this->hasForbiddenProductMutation($source), "Fixture {$fixture} must trip the ratchet.");
    }

    public function test_ratchet_allows_a_product_read_fixture(): void
    {
        self::assertFalse($this->hasForbiddenProductMutation('<?php App\\Models\\Product::query()->whereKey(1)->first();'));
    }

    private function hasForbiddenProductMutation(string $source): bool
    {
        $modelNames = ['Product', '\\App\\Models\\Product'];
        if (preg_match_all('/use\s+App\\\\Models\\\\Product(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?\s*;/', $source, $matches) > 0) {
            foreach ($matches[1] as $alias) {
                if ($alias !== '') {
                    $modelNames[] = $alias;
                }
            }
        }
        $model = '(?:'.implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), array_unique($modelNames))).')';
        $write = '(?:save|saveQuietly|update|updateQuietly|delete|forceDelete|restore|forceFill|increment|decrement)';

        return preg_match('/'.$model.'\s*::\s*(?:create|forceCreate|updateOrCreate|firstOrCreate|upsert|insert)\s*\(/i', $source) === 1
            || preg_match('/\$product\s*->\s*'.$write.'\s*\(/i', $source) === 1
            || preg_match('/'.$model.'\s+\$(record|product)\b[\s\S]{0,1500}?\$\1\s*->\s*'.$write.'\s*\(/i', $source) === 1
            || preg_match('/\$([A-Za-z_][A-Za-z0-9_]*)\s*=\s*new\s+'.$model.'\b[^;]*;[\s\S]{0,1500}?\$\1\s*->\s*'.$write.'\s*\(/i', $source) === 1
            || preg_match('/\$([A-Za-z_][A-Za-z0-9_]*)\s*=\s*'.$model.'\s*::[^;]+;[\s\S]{0,1500}?\$\1\s*->\s*'.$write.'\s*\(/i', $source) === 1
            || preg_match('/'.$model.'\s*::[^;]{0,2000}?->\s*(?:update|updateQuietly|delete|forceDelete|restore|increment|decrement)\s*\(/i', $source) === 1
            || preg_match('/\bDB\s*::\s*table\s*\(\s*[\'"]products[\'"]\s*\)[^;]{0,3000}?->\s*(?:insert|update|delete|upsert|increment|decrement)\s*\(/i', $source) === 1
            || preg_match('/->\s*products\s*\(\s*\)\s*->\s*(?:create|save|update|delete|upsert)\s*\(/i', $source) === 1;
    }

    /** @return array<string, array{string}> */
    public static function forbiddenMutationFixtures(): array
    {
        return [
            'static create' => ['ForbiddenProductMutation.php.fixture'],
            'new product save' => ['NewProductSave.php.fixture'],
            'typed record mutation' => ['TypedProductRecord.php.fixture'],
            'product query update' => ['ProductQueryUpdate.php.fixture'],
            'products table upsert' => ['ProductsTableUpsert.php.fixture'],
            'untyped product variable' => ['UntypedProductVariable.php.fixture'],
            'aliased product model' => ['AliasedProductMutation.php.fixture'],
            'product relationship create' => ['ProductRelationshipCreate.php.fixture'],
        ];
    }
}
