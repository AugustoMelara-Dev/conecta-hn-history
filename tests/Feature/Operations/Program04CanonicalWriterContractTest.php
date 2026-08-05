<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Crm\CanonicalOpportunityWriter;
use App\Application\Orders\CanonicalOrderWriter;
use App\Application\Products\CanonicalProductWriter;
use App\Application\Quotes\CanonicalQuoteWriter;
use App\Application\Reservations\CanonicalReservationWriter;
use App\Application\ServiceCatalog\CanonicalServiceWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class Program04CanonicalWriterContractTest extends TestCase
{
    /** @var array<string, class-string> */
    private const WRITERS = [
        'product' => CanonicalProductWriter::class,
        'service' => CanonicalServiceWriter::class,
        'opportunity' => CanonicalOpportunityWriter::class,
        'order' => CanonicalOrderWriter::class,
        'reservation' => CanonicalReservationWriter::class,
        'quote' => CanonicalQuoteWriter::class,
    ];

    /** @var list<string> */
    private const NARROW_INFRASTRUCTURE_WRITE_OWNERS = [
        'app/Infrastructure/Repositories/Products/EloquentProductBlurhashWriter.php',
        'app/Infrastructure/Repositories/Products/EloquentProductRatingMetricsWriter.php',
    ];

    public function test_each_operational_aggregate_has_one_explicit_canonical_writer(): void
    {
        foreach (self::WRITERS as $aggregate => $writer) {
            self::assertTrue(class_exists($writer), "{$aggregate} canonical writer is missing.");
        }
        self::assertCount(6, array_unique(self::WRITERS));
    }

    public function test_http_filament_and_infrastructure_adapters_do_not_mutate_aggregates_directly(): void
    {
        $root = dirname(__DIR__, 3);
        $violations = [];
        foreach (['app/Http', 'app/Filament', 'app/Infrastructure'] as $directory) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root.'/'.$directory),
            );
            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo
                    || ! $file->isFile()
                    || $file->getExtension() !== 'php') {
                    continue;
                }
                $relative = str_replace(
                    '\\',
                    '/',
                    substr($file->getPathname(), strlen($root) + 1),
                );
                if (in_array($relative, self::NARROW_INFRASTRUCTURE_WRITE_OWNERS, true)) {
                    continue;
                }
                $source = $this->withoutComments((string) file_get_contents($file->getPathname()));
                if ($this->hasDirectAggregateMutation($source)) {
                    $violations[] = $relative;
                }
            }
        }

        self::assertSame([], array_values(array_unique($violations)));
    }

    #[DataProvider('mutationFixtures')]
    public function test_scanner_rejects_a_representative_mutation_for_every_aggregate(
        string $source,
    ): void {
        self::assertTrue($this->hasDirectAggregateMutation($source));
    }

    public function test_scanner_allows_reads_and_canonical_writer_calls(): void
    {
        self::assertFalse($this->hasDirectAggregateMutation(
            '<?php Product::query()->whereKey(1)->first(); $writer->create($command);',
        ));
    }

    private function hasDirectAggregateMutation(string $source): bool
    {
        $models = '(?:Product|Service|CrmDeal|Order|Appointment|Quote)';
        $tables = '(?:products|services|crm_deals|orders|order_items|appointments|quotes|quote_items)';
        $writes = '(?:create|forceCreate|updateOrCreate|firstOrCreate|insert|insertGetId|upsert|update|delete)';
        $instanceWrites = '(?:save|saveQuietly|update|updateQuietly|delete|forceDelete|forceFill)';

        return preg_match('/\b'.$models.'\s*::\s*'.$writes.'\s*\(/', $source) === 1
            || preg_match('/\b'.$models.'\s*::[^;]{0,800}?->\s*'.$writes.'\s*\(/', $source) === 1
            || preg_match('/\bnew\s+'.$models.'\b[\s\S]{0,1200}?->\s*'.$instanceWrites.'\s*\(/', $source) === 1
            || preg_match('/\$(?:product|service|deal|order|appointment|quote)\s*->\s*'.$instanceWrites.'\s*\(/', $source) === 1
            || preg_match('/DB\s*::\s*table\s*\(\s*[\'"]'.$tables.'[\'"]\s*\)[\s\S]{0,1600}?->\s*'.$writes.'\s*\(/', $source) === 1
            || preg_match('/->\s*(?:products|services|deals|orders|appointments|quotes|items)\s*\(\s*\)\s*->\s*'.$writes.'\s*\(/', $source) === 1;
    }

    private function withoutComments(string $source): string
    {
        $clean = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $clean .= is_array($token) ? $token[1] : $token;
        }

        return $clean;
    }

    /** @return array<string, array{string}> */
    public static function mutationFixtures(): array
    {
        return [
            'product' => ['<?php Product::forceCreate([]);'],
            'service' => ['<?php $service->update([]);'],
            'opportunity' => ["<?php DB::table('crm_deals')->insert([]);"],
            'order' => ['<?php new Order()->save();'],
            'reservation' => ['<?php Appointment::query()->delete();'],
            'quote' => ['<?php $quote->forceFill([])->save();'],
        ];
    }
}
