<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ServiceWriteOwnershipRatchetTest extends TestCase
{
    /** @var list<string> */
    private const ALLOWED_WRITE_OWNERS = [
        'app/Application/ServiceCatalog/CanonicalServiceWriter.php',
        'app/Application/Operations/BusinessOperationsBackfill.php',
    ];

    public function test_operational_adapters_cannot_mutate_services_or_providers_outside_documented_owners(): void
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
            'Operational Service and ServiceProvider mutations must use the canonical writer.',
        );
    }

    #[DataProvider('forbiddenMutationFixtures')]
    public function test_ratchet_detects_prohibited_direct_mutations(string $source): void
    {
        self::assertTrue($this->hasForbiddenMutation($source));
    }

    public function test_ratchet_allows_service_reads(): void
    {
        self::assertFalse($this->hasForbiddenMutation(
            '<?php App\Models\Service::query()->whereKey(1)->first();',
        ));
    }

    private function hasForbiddenMutation(string $source): bool
    {
        $models = [
            'Service',
            'ServiceProvider',
            'App\Models\Service',
            'App\Models\ServiceProvider',
            '\App\Models\Service',
            '\App\Models\ServiceProvider',
        ];
        if (preg_match_all(
            '/use\s+App\\\\Models\\\\(Service|ServiceProvider)(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?\s*;/',
            $source,
            $matches,
            PREG_SET_ORDER,
        ) > 0) {
            foreach ($matches as $match) {
                if (($match[2] ?? '') !== '') {
                    $models[] = $match[2];
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
            || preg_match('/\bDB\s*::\s*table\s*\(\s*[\'"](?:services|service_providers)[\'"]\s*\)[^;]{0,3000}?->\s*(?:insert|insertGetId|update|delete|upsert|updateOrInsert|increment|decrement)\s*\(/i', $source) === 1
            || preg_match('/->\s*(?:services|serviceProviders)\s*\(\s*\)\s*->\s*(?:create|save|update|delete|upsert)\s*\(/i', $source) === 1;
    }

    /** @return array<string, array{string}> */
    public static function forbiddenMutationFixtures(): array
    {
        return [
            'static service create' => ['<?php App\Models\Service::create([]);'],
            'aliased provider create' => ['<?php use App\Models\ServiceProvider as Staff; Staff::create([]);'],
            'new service save' => ['<?php $item = new App\Models\Service; $item->save();'],
            'typed provider update' => ['<?php function mutate(App\Models\ServiceProvider $provider) { $provider->update([]); }'],
            'service query delete' => ['<?php App\Models\Service::query()->whereKey(1)->delete();'],
            'raw service upsert' => ["<?php DB::table('services')->upsert([], ['id']);"],
            'provider relationship create' => ['<?php $tenant->serviceProviders()->create([]);'],
        ];
    }
}
