<?php

declare(strict_types=1);

use App\Application\Ai\Registry\RegistryMutationScanner;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;

it('covers every relevant public laravel mutation method in the scanner inventory', function (): void {
    $inventory = RegistryMutationScanner::mutationInventory();
    $publicMethods = static fn (string $class): array => collect(
        (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC),
    )
        ->pluck('name')
        ->unique()
        ->values()
        ->all();
    $queryMutations = collect($publicMethods(QueryBuilder::class))
        ->filter(static fn (string $method): bool => preg_match(
            '/^(?:insert|update|upsert|delete|truncate|increment|decrement)/',
            $method,
        ) === 1)
        ->sort()
        ->values()
        ->all();
    $eloquentMutations = collect([
        ...$publicMethods(EloquentBuilder::class),
        ...$publicMethods(Model::class),
        ...$publicMethods(HasTimestamps::class),
        ...$publicMethods(SoftDeletes::class),
    ])
        ->filter(static fn (string $method): bool => preg_match(
            '/^(?:fillAndInsert|create|firstOrCreate|update|upsert|push|save|destroy|delete|forceDelete|forceDestroy|touch|restore|increment|decrement)/',
            $method,
        ) === 1)
        ->reject(static fn (string $method): bool => in_array($method, [
            'created',
            'updated',
            'saved',
            'deleted',
            'restored',
            'restoring',
            'forceDeleted',
            'forceDeleting',
            'updateTimestamps',
            'touchOwners',
            'touches',
        ], true))
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect(array_values(array_diff($queryMutations, $inventory['query'])))->toBe([])
        ->and(array_values(array_diff($eloquentMutations, $inventory['eloquent'])))->toBe([]);
});

it('detects the complete laravel 13 query eloquent and model mutation families', function (): void {
    $base = dirname(__DIR__, 2);
    $root = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
        .DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'registry-ratchet-'.Str::uuid();
    mkdir($root, 0777, true);
    $modelFixture = <<<'PHP'
<?php

namespace Fixtures;

use Illuminate\Database\Eloquent\Model;

final class ProviderVersion extends Model
{
    protected $table = 'ai_provider_versions';
}
PHP;
    $fixture = <<<'PHP'
<?php

use Fixtures\ProviderVersion as RegistryProvider;
use Illuminate\Support\Facades\DB as Storage;

Storage::table('ai_agent_versions')->insertUsing(['id'], Storage::table('source'));
Storage::table('ai_agent_versions')->insertOrIgnoreUsing(['id'], Storage::table('source'));
Storage::table('ai_agent_versions')->insertOrIgnoreReturning([], ['id']);
Storage::table('ai_agent_versions')->updateFrom([]);

RegistryProvider::fillAndInsert([]);
RegistryProvider::fillAndInsertOrIgnore([]);
RegistryProvider::fillAndInsertGetId([]);
RegistryProvider::createQuietly([]);
RegistryProvider::createOrFirst([]);
RegistryProvider::incrementOrCreate([], 'version');
RegistryProvider::incrementEach(['version' => 1]);
RegistryProvider::decrementEach(['version' => 1]);
RegistryProvider::touch();
RegistryProvider::forceDestroy(['fixture']);

$provider = new RegistryProvider();
$provider->updateOrFail([]);
$provider->deleteOrFail();
$provider->saveOrIgnore();
$provider->touchQuietly();
$provider->restore();
$provider->restoreQuietly();
PHP;
    file_put_contents($root.DIRECTORY_SEPARATOR.'ProviderVersion.php', $modelFixture);
    file_put_contents($root.DIRECTORY_SEPARATOR.'Laravel13Writes.php', $fixture);

    try {
        $findings = (new RegistryMutationScanner)->scan(
            [$root],
            ['ai_provider_versions', 'ai_agent_versions'],
        );
    } finally {
        unlink($root.DIRECTORY_SEPARATOR.'Laravel13Writes.php');
        unlink($root.DIRECTORY_SEPARATOR.'ProviderVersion.php');
        rmdir($root);
    }

    expect(collect($findings)
        ->map(static fn (object $finding): string => $finding->table.':'.$finding->method)
        ->sort()
        ->values()
        ->all())->toBe([
            'ai_agent_versions:insertOrIgnoreReturning',
            'ai_agent_versions:insertOrIgnoreUsing',
            'ai_agent_versions:insertUsing',
            'ai_agent_versions:updateFrom',
            'ai_provider_versions:createOrFirst',
            'ai_provider_versions:createQuietly',
            'ai_provider_versions:decrementEach',
            'ai_provider_versions:deleteOrFail',
            'ai_provider_versions:fillAndInsert',
            'ai_provider_versions:fillAndInsertGetId',
            'ai_provider_versions:fillAndInsertOrIgnore',
            'ai_provider_versions:forceDestroy',
            'ai_provider_versions:incrementEach',
            'ai_provider_versions:incrementOrCreate',
            'ai_provider_versions:restore',
            'ai_provider_versions:restoreQuietly',
            'ai_provider_versions:saveOrIgnore',
            'ai_provider_versions:touch',
            'ai_provider_versions:touchQuietly',
            'ai_provider_versions:updateOrFail',
        ]);
});

it('reports dynamic direct sql and unions conditional registry origins conservatively', function (): void {
    $base = dirname(__DIR__, 2);
    $root = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
        .DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'registry-ratchet-'.Str::uuid();
    mkdir($root, 0777, true);
    $fixture = <<<'PHP'
<?php

use Illuminate\Support\Facades\DB as Storage;

$sql = getenv('REGISTRY_SQL');
Storage::statement($sql);
Storage::unprepared($sql);
Storage::insert($sql);
Storage::update($sql);
Storage::delete($sql);
Storage::affectingStatement($sql);
$ids = '?,?';
Storage::affectingStatement("UPDATE outbox_messages SET status = 'done' WHERE id IN ($ids)");

$branch = null;
if (random_int(0, 1) === 1) {
    $branch = Storage::table('ai_agent_versions');
}
$branch->delete();

$ternary = random_int(0, 1) === 1
    ? Storage::table('ai_prompt_versions')
    : null;
$ternary->update([]);

$matched = match (random_int(0, 2)) {
    0 => null,
    1 => Storage::table('orders'),
    default => Storage::table('ai_model_versions'),
};
$matched->truncate();
PHP;
    file_put_contents($root.DIRECTORY_SEPARATOR.'DynamicAndConditional.php', $fixture);

    try {
        $findings = (new RegistryMutationScanner)->scan(
            [$root],
            ['ai_agent_versions', 'ai_prompt_versions', 'ai_model_versions'],
        );
    } finally {
        unlink($root.DIRECTORY_SEPARATOR.'DynamicAndConditional.php');
        rmdir($root);
    }

    expect(collect($findings)
        ->map(static fn (object $finding): string => $finding->table.':'.$finding->method)
        ->countBy()
        ->sortKeys()
        ->all())->toBe([
            '*dynamic*:affectingStatement' => 1,
            '*dynamic*:delete' => 1,
            '*dynamic*:insert' => 1,
            '*dynamic*:raw' => 2,
            '*dynamic*:update' => 1,
            'ai_agent_versions:delete' => 1,
            'ai_model_versions:truncate' => 1,
            'ai_prompt_versions:update' => 1,
        ]);
});

it('distinguishes interpolated sql targets and detects case insensitive and dynamic methods', function (): void {
    $base = dirname(__DIR__, 2);
    $root = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
        .DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'registry-ratchet-'.Str::uuid();
    mkdir($root, 0777, true);
    $modelFixture = <<<'PHP'
<?php

namespace Fixtures;

use Illuminate\Database\Eloquent\Model;

final class ProviderVersion extends Model
{
    protected $table = 'ai_provider_versions';
}
PHP;
    $fixture = <<<'PHP'
<?php

use Fixtures\ProviderVersion as RegistryProvider;
use Illuminate\Support\Facades\DB as Storage;

$table = getenv('REGISTRY_TABLE');
$value = getenv('REGISTRY_VALUE');
$method = getenv('REGISTRY_METHOD');

Storage::statement("UPDATE {$table} SET checksum = 'fixed'");
Storage::statement("INSERT INTO {$table} (id) VALUES ('fixture')");
Storage::statement("DELETE FROM {$table} WHERE id = 'fixture'");
Storage::statement("TRUNCATE TABLE {$table}");
Storage::statement("ALTER TABLE {$table} ADD COLUMN fixture INT");
Storage::statement("DROP TABLE IF EXISTS {$table}");

Storage::StAtEmEnT("UPDATE ai_agent_versions SET checksum = '{$value}'");
Storage::statement("UPDATE outbox_messages SET payload = '{$value}'");

Storage::TaBlE('ai_agent_versions')->UPDATE([]);
Storage::table('ai_agent_versions')->InSeRtUsInG(['id'], Storage::table('source'));
RegistryProvider::DeLeTe();

$provider = new RegistryProvider();
$provider?->SaVeQuIeTlY();

$registry = Storage::table('ai_agent_versions');
$registry->{$method}([]);
$orders = Storage::table('orders');
$orders->{$method}([]);
RegistryProvider::$method([]);
PHP;
    file_put_contents($root.DIRECTORY_SEPARATOR.'ProviderVersion.php', $modelFixture);
    file_put_contents($root.DIRECTORY_SEPARATOR.'InterpolationAndCase.php', $fixture);

    try {
        $findings = (new RegistryMutationScanner)->scan(
            [$root],
            ['ai_agent_versions', 'ai_provider_versions'],
        );
    } finally {
        unlink($root.DIRECTORY_SEPARATOR.'InterpolationAndCase.php');
        unlink($root.DIRECTORY_SEPARATOR.'ProviderVersion.php');
        rmdir($root);
    }

    expect(collect($findings)
        ->map(static fn (object $finding): string => $finding->table.':'.$finding->method)
        ->countBy()
        ->sortKeys()
        ->all())->toBe([
            '*dynamic*:raw' => 6,
            'ai_agent_versions:*dynamic_method*' => 1,
            'ai_agent_versions:insertUsing' => 1,
            'ai_agent_versions:raw' => 1,
            'ai_agent_versions:update' => 1,
            'ai_provider_versions:*dynamic_method*' => 1,
            'ai_provider_versions:delete' => 1,
            'ai_provider_versions:saveQuietly' => 1,
        ]);
});

it('detects sql target modifiers and comments without comment or value false positives', function (): void {
    $base = dirname(__DIR__, 2);
    $root = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
        .DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'registry-ratchet-'.Str::uuid();
    mkdir($root, 0777, true);
    $fixture = <<<'PHP'
<?php

use Illuminate\Support\Facades\DB as Storage;

$table = getenv('REGISTRY_TABLE');

Storage::statement('UPDATE ONLY ai_agent_versions SET checksum = 1');
Storage::statement('ALTER TABLE ONLY ai_agent_versions ADD COLUMN fixture INT');
Storage::statement('UPDATE /* governed */ ai_agent_versions SET checksum = 2');
Storage::statement("UPDATE -- governed\n ai_model_versions SET version = 2");
Storage::statement('DELETE FROM /* governed */ ONLY ai_prompt_versions WHERE id = 1');
Storage::statement('TRUNCATE TABLE /* governed */ ONLY ai_provider_versions');
Storage::statement('ALTER /* governed */ TABLE ONLY ai_release_policies ADD COLUMN fixture INT');
Storage::statement("UPDATE # governed\n ai_agent_activations SET lock_version = 2");
Storage::statement('UPDATE LOW_PRIORITY ai_release_decisions SET status = 1');

Storage::statement('UPDATE orders /* ai_agent_versions */ SET status = 1');
Storage::statement("UPDATE orders -- ai_model_versions\n SET status = 1");
Storage::statement("UPDATE orders # ai_prompt_versions\n SET status = 1");
Storage::statement("UPDATE orders SET note = 'ai_provider_versions'");
Storage::statement("UPDATE orders SET note = '/* ai_release_policies */'");
Storage::statement("UPDATE orders /* {$table} */ SET status = 1");
Storage::statement('UPDATE orders SET source_table = ai_release_decisions');
Storage::statement('UPDATE orders SET status = ai_agent_activations.status FROM ai_agent_activations');
Storage::statement('INSERT INTO orders (note) SELECT checksum FROM ai_agent_versions');
PHP;
    file_put_contents($root.DIRECTORY_SEPARATOR.'SqlTargetModifiers.php', $fixture);

    try {
        $findings = (new RegistryMutationScanner)->scan(
            [$root],
            [
                'ai_provider_versions',
                'ai_model_versions',
                'ai_agent_versions',
                'ai_prompt_versions',
                'ai_release_policies',
                'ai_agent_activations',
                'ai_release_decisions',
            ],
        );
    } finally {
        unlink($root.DIRECTORY_SEPARATOR.'SqlTargetModifiers.php');
        rmdir($root);
    }

    expect(collect($findings)
        ->map(static fn (object $finding): string => $finding->table.':'.$finding->method)
        ->countBy()
        ->sortKeys()
        ->all())->toBe([
            'ai_agent_activations:raw' => 1,
            'ai_agent_versions:raw' => 3,
            'ai_model_versions:raw' => 1,
            'ai_prompt_versions:raw' => 1,
            'ai_provider_versions:raw' => 1,
            'ai_release_decisions:raw' => 1,
            'ai_release_policies:raw' => 1,
        ]);
});

it('distinguishes delete targets from join and using sources', function (): void {
    $base = dirname(__DIR__, 2);
    $root = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
        .DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'registry-ratchet-'.Str::uuid();
    mkdir($root, 0777, true);
    $fixture = <<<'PHP'
<?php

use Illuminate\Support\Facades\DB as Storage;

$table = getenv('REGISTRY_TABLE');

Storage::statement('DELETE orders FROM orders JOIN ai_agent_versions ON ai_agent_versions.id = orders.id');
Storage::statement('DELETE registry_alias FROM ai_agent_versions AS registry_alias JOIN orders ON orders.id = registry_alias.id');
Storage::statement('DELETE /* governed */ registry_alias.* FROM ai_model_versions AS registry_alias JOIN orders ON orders.id = registry_alias.id');
Storage::statement('DELETE a,b FROM ai_prompt_versions AS a JOIN orders AS b ON b.id = a.id JOIN ai_provider_versions AS source ON source.id = a.id');
Storage::statement('DELETE FROM governed USING ai_agent_activations AS governed JOIN orders ON orders.id = governed.id');
Storage::statement('DELETE FROM orders USING orders JOIN ai_release_decisions ON ai_release_decisions.id = orders.id');
Storage::statement('DELETE FROM /* governed */ ONLY ai_release_policies WHERE id = 1');
Storage::statement('DELETE ai_release_decisions,orders FROM ai_release_decisions JOIN orders ON orders.id = ai_release_decisions.id');
Storage::statement('DELETE orders.* FROM orders JOIN ai_provider_versions ON ai_provider_versions.id = orders.id');
Storage::statement('DELETE FROM ai_provider_versions AS governed USING orders WHERE orders.id = governed.id');
Storage::statement("DELETE /* governed */ {$table} FROM orders AS source");
Storage::statement("DELETE FROM {$table} USING orders");
PHP;
    file_put_contents($root.DIRECTORY_SEPARATOR.'DeleteTargets.php', $fixture);

    try {
        $findings = (new RegistryMutationScanner)->scan(
            [$root],
            [
                'ai_provider_versions',
                'ai_model_versions',
                'ai_agent_versions',
                'ai_prompt_versions',
                'ai_release_policies',
                'ai_agent_activations',
                'ai_release_decisions',
            ],
        );
    } finally {
        unlink($root.DIRECTORY_SEPARATOR.'DeleteTargets.php');
        rmdir($root);
    }

    expect(collect($findings)
        ->map(static fn (object $finding): string => $finding->table.':'.$finding->method)
        ->countBy()
        ->sortKeys()
        ->all())->toBe([
            '*dynamic*:raw' => 3,
            'ai_agent_versions:raw' => 1,
            'ai_model_versions:raw' => 1,
            'ai_prompt_versions:raw' => 1,
            'ai_provider_versions:raw' => 1,
            'ai_release_decisions:raw' => 1,
            'ai_release_policies:raw' => 1,
        ]);
});

it('tokenizes delete grammar without alias shadow or quoted keyword boundaries', function (): void {
    $base = dirname(__DIR__, 2);
    $root = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
        .DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'registry-ratchet-'.Str::uuid();
    mkdir($root, 0777, true);
    $fixture = <<<'PHP'
<?php

use Illuminate\Support\Facades\DB as Storage;

$table = getenv('REGISTRY_TABLE');

Storage::statement('DELETE ai_agent_versions FROM orders AS ai_agent_versions JOIN ai_agent_versions AS registry_source ON 1=1');
Storage::statement('DELETE governed FROM orders AS source STRAIGHT_JOIN ai_agent_versions AS governed ON 1=1');
Storage::statement('DELETE governed FROM orders AS source JOIN audits AS audit ON audit."where" = source.id JOIN ai_agent_versions AS governed ON 1=1');

Storage::statement('DELETE LOW_PRIORITY QUICK IGNORE FROM ai_provider_versions PARTITION (p0) AS governed WHERE governed.id = 1');
Storage::statement('DELETE FROM ONLY "public"."ai_model_versions" * AS governed USING audits WHERE audits.id = governed.id');
Storage::statement('DELETE governed.*, audit_alias FROM "public"."ai_prompt_versions" AS governed INNER JOIN orders AS audit_alias ON audit_alias.id = governed.id');
Storage::statement("DELETE governed FROM orders AS source LEFT JOIN audits AS audit ON (audit.note = 'where' AND EXISTS (SELECT 1 FROM logs WHERE logs.id = audit.id)) /* where join */ NATURAL RIGHT JOIN ai_release_policies AS governed");
Storage::statement('DELETE governed FROM orders AS source RIGHT JOIN ai_release_decisions PARTITION (p0) AS governed ON (governed.id = (SELECT max(id) FROM audits WHERE audits.order_id = source.id))');
Storage::statement('DELETE governed FROM orders AS source JOIN audits AS audit USING ("where") STRAIGHT_JOIN ai_agent_activations AS governed ON governed.id = source.id');
Storage::statement('DELETE governed, audit FROM ai_agent_definitions AS governed JOIN audits AS audit ON audit.id = governed.id JOIN ai_models AS registry_source ON registry_source.id = governed.id');
Storage::statement('DELETE FROM ai_prompt_definitions AS governed WHERE governed.id = 1');
Storage::statement('DELETE governed FROM ai_agent_activations AS governed JOIN orders AS governed ON governed.id = governed.id');
Storage::statement("DELETE {$table} FROM orders AS source");
Storage::statement('DELETE "public"."ai_models" FROM "public"."ai_models" WHERE id = 1');
Storage::statement('DELETE FROM governed USING ai_providers AS governed JOIN orders ON orders.id = governed.id');
Storage::statement('DELETE FROM governed.*, audit USING ai_prompt_versions AS governed JOIN orders AS audit ON audit.id = governed.id');
Storage::statement('DELETE FROM "where" USING ai_agent_versions AS "where" JOIN orders ON orders.id = "where".id');

Storage::statement('DELETE FROM governed USING orders AS governed JOIN ai_provider_versions AS registry_source ON registry_source.id = governed.id');
Storage::statement('DELETE FROM ONLY orders * AS governed USING ai_release_policies AS registry_source WHERE registry_source.id = governed.id');
Storage::statement('DELETE orders FROM orders CROSS JOIN ai_agent_versions INNER JOIN ai_model_versions NATURAL LEFT JOIN ai_prompt_versions RIGHT JOIN ai_provider_versions ON 1=1');
Storage::statement("DELETE orders FROM orders JOIN {$table} AS registry_source ON 1=1");
Storage::statement("DELETE orders FROM orders JOIN audits ON audits.note = 'WHERE JOIN ai_agent_versions AS governed' /* STRAIGHT_JOIN ai_model_versions */");
Storage::statement('DELETE FROM "audit.ai_agent_versions" WHERE id = 1');
PHP;
    file_put_contents($root.DIRECTORY_SEPARATOR.'DeleteTokenizer.php', $fixture);

    try {
        $findings = (new RegistryMutationScanner)->scan(
            [$root],
            [
                'ai_providers',
                'ai_provider_versions',
                'ai_models',
                'ai_model_versions',
                'ai_agent_definitions',
                'ai_agent_versions',
                'ai_prompt_definitions',
                'ai_prompt_versions',
                'ai_release_policies',
                'ai_agent_activations',
                'ai_release_decisions',
            ],
        );
    } finally {
        unlink($root.DIRECTORY_SEPARATOR.'DeleteTokenizer.php');
        rmdir($root);
    }

    expect(collect($findings)
        ->map(static fn (object $finding): string => $finding->table.':'.$finding->method)
        ->countBy()
        ->sortKeys()
        ->all())->toBe([
            '*dynamic*:raw' => 4,
            'ai_agent_activations:raw' => 1,
            'ai_agent_definitions:raw' => 1,
            'ai_agent_versions:raw' => 2,
            'ai_model_versions:raw' => 1,
            'ai_models:raw' => 1,
            'ai_prompt_definitions:raw' => 1,
            'ai_prompt_versions:raw' => 2,
            'ai_provider_versions:raw' => 1,
            'ai_release_decisions:raw' => 1,
            'ai_release_policies:raw' => 1,
        ]);
});

it('resolves parser backed delete targets across dialect and interpolation boundaries', function (): void {
    $base = dirname(__DIR__, 2);
    $root = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
        .DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'registry-ratchet-'.Str::uuid();
    mkdir($root, 0777, true);
    $fixture = <<<'PHP'
<?php

use Illuminate\Support\Facades\DB as Storage;

$suffix = getenv('REGISTRY_SUFFIX');

Storage::statement('DELETE FROM ai_agent_versions AS governed USING orders AS ai_agent_versions WHERE governed.id = ai_agent_versions.id');
Storage::statement('DELETE FROM ai_release_decisions USING orders AS ai_release_decisions WHERE orders.id = ai_release_decisions.id');
Storage::statement('DELETE governed FROM (ai_agent_versions AS governed JOIN orders AS source ON source.id = governed.id)');
Storage::statement("DELETE ai_{$suffix} FROM ai_agent_versions AS ai_agent_versions");

Storage::statement('DELETE FROM ONLY "public"."ai_model_versions" * AS governed USING orders AS ai_agent_versions WHERE governed.id = ai_agent_versions.id');
Storage::statement('DELETE governed.* FROM ("public"."ai_prompt_versions" AS governed STRAIGHT_JOIN orders AS source ON source.id = governed.id)');
Storage::statement('DELETE FROM governed USING (ai_release_policies AS governed JOIN orders AS source ON source.id = governed.id)');
Storage::statement("DELETE {$suffix}_versions FROM ai_agent_versions AS governed");
Storage::statement('DELETE governed FROM ai_agent_activations AS governed JOIN orders AS governed ON governed.id = governed.id');
Storage::statement('DELETE governed USING ai_agent_versions');

Storage::statement('DELETE FROM orders AS governed USING audits AS ai_agent_versions WHERE governed.id = ai_agent_versions.id');
Storage::statement('DELETE orders FROM (orders JOIN ai_agent_versions AS registry_source ON registry_source.id = orders.id)');
Storage::statement("DELETE orders FROM orders JOIN ai_{$suffix} AS registry_source ON registry_source.id = orders.id");
Storage::statement("DELETE orders FROM orders JOIN audits AS source ON source.note = 'ai_agent_versions {$suffix}' /* ai_release_decisions */");
Storage::statement('DELETE FROM orders USING (orders JOIN ai_release_decisions AS registry_source ON registry_source.id = orders.id)');
PHP;
    file_put_contents($root.DIRECTORY_SEPARATOR.'ParserBackedDeleteTargets.php', $fixture);

    try {
        $findings = (new RegistryMutationScanner)->scan(
            [$root],
            [
                'ai_agent_versions',
                'ai_model_versions',
                'ai_prompt_versions',
                'ai_release_policies',
                'ai_agent_activations',
                'ai_release_decisions',
            ],
        );
    } finally {
        unlink($root.DIRECTORY_SEPARATOR.'ParserBackedDeleteTargets.php');
        rmdir($root);
    }

    expect(collect($findings)
        ->map(static fn (object $finding): string => $finding->table.':'.$finding->method)
        ->countBy()
        ->sortKeys()
        ->all())->toBe([
            '*dynamic*:raw' => 6,
            'ai_agent_versions:raw' => 2,
            'ai_model_versions:raw' => 1,
            'ai_prompt_versions:raw' => 1,
        ]);
});

it('compares ambiguous delete dialect targets and rejects malformed target contexts', function (): void {
    $base = dirname(__DIR__, 2);
    $root = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
        .DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'registry-ratchet-'.Str::uuid();
    mkdir($root, 0777, true);
    $fixture = <<<'PHP'
<?php

use Illuminate\Support\Facades\DB as Storage;

Storage::statement('DELETE FROM orders USING ai_agent_versions AS orders WHERE orders.id = ai_agent_versions.id');
Storage::statement('DELETE FROM ai_prompt_versions USING orders AS ai_prompt_versions WHERE orders.id = ai_prompt_versions.id');
Storage::statement('DELETE FROM ai_agent_versions USING ai_agent_versions WHERE id = 1');
Storage::statement('DELETE FROM orders USING orders WHERE id = 1');
Storage::statement('DELETE FROM ai_model_versions AS governed USING orders AS ai_model_versions WHERE governed.id = ai_model_versions.id');

Storage::statement('DELETE FROM ai_release_policies PARTITION (p0) WHERE id = 1');
Storage::statement('DELETE FROM orders PARTITION (p0) WHERE id = 1');
Storage::statement('DELETE PARTITION (ai_agent_versions) FROM orders');
Storage::statement('DELETE FROM orders WHERE (orders PARTITION (ai_agent_versions))');
Storage::statement('DELETE FROM WHERE ai_agent_versions');
Storage::statement('DELETE FROM "where" WHERE id = 1');

Storage::statement("DELETE FROM orders AS target USING ai_release_decisions AS registry_source WHERE target.note = 'ai_prompt_versions' /* ai_agent_versions */");
PHP;
    file_put_contents($root.DIRECTORY_SEPARATOR.'DeleteDialectTargets.php', $fixture);

    try {
        $findings = (new RegistryMutationScanner)->scan(
            [$root],
            [
                'where',
                'ai_agent_versions',
                'ai_model_versions',
                'ai_prompt_versions',
                'ai_release_policies',
                'ai_release_decisions',
            ],
        );
    } finally {
        unlink($root.DIRECTORY_SEPARATOR.'DeleteDialectTargets.php');
        rmdir($root);
    }

    expect(collect($findings)
        ->map(static fn (object $finding): string => $finding->table.':'.$finding->method)
        ->countBy()
        ->sortKeys()
        ->all())->toBe([
            '*dynamic*:raw' => 5,
            'ai_agent_versions:raw' => 1,
            'ai_model_versions:raw' => 1,
            'ai_release_policies:raw' => 1,
            'where:raw' => 1,
        ]);
});

it('rejects postgres target boundaries without invalidating valid delete tails', function (): void {
    $base = dirname(__DIR__, 2);
    $root = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
        .DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'registry-ratchet-'.Str::uuid();
    mkdir($root, 0777, true);
    $fixture = <<<'PHP'
<?php

use Illuminate\Support\Facades\DB as Storage;

Storage::statement('DELETE FROM RETURNING ai_agent_versions');
Storage::statement('DELETE FROM Returning ai_agent_versions');
Storage::statement('DELETE FROM returning ai_agent_versions');

Storage::statement('DELETE FROM "returning" WHERE id = 1');
Storage::statement('DELETE FROM `returning` WHERE id = 1');
Storage::statement('DELETE FROM [returning] WHERE id = 1');

Storage::statement('DELETE FROM ai_agent_versions RETURNING id');
Storage::statement('DELETE FROM ai_agent_versions AS governed ReTuRnInG governed.id');
Storage::statement('DELETE FROM ai_agent_versions AS ReTuRnInG WHERE id = 1');
Storage::statement('DELETE FROM ai_prompt_versions WHERE CURRENT OF prompt_cursor');

Storage::statement('DELETE FROM orders /* RETURNING ai_agent_versions */ WHERE id = 1');
Storage::statement("DELETE FROM orders WHERE note = 'RETURNING ai_agent_versions'");
Storage::statement('DELETE FROM orders WHERE note = $tag$RETURNING ai_agent_versions$tag$');
Storage::statement('DELETE FROM orders RETURNING ai_agent_versions');
PHP;
    file_put_contents($root.DIRECTORY_SEPARATOR.'DeleteTargetBoundaries.php', $fixture);

    try {
        $findings = (new RegistryMutationScanner)->scan(
            [$root],
            [
                'returning',
                'ai_agent_versions',
                'ai_prompt_versions',
            ],
        );
    } finally {
        unlink($root.DIRECTORY_SEPARATOR.'DeleteTargetBoundaries.php');
        rmdir($root);
    }

    expect(collect($findings)
        ->map(static fn (object $finding): string => $finding->table.':'.$finding->method)
        ->countBy()
        ->sortKeys()
        ->all())->toBe([
            '*dynamic*:raw' => 5,
            'ai_agent_versions:raw' => 2,
            'ai_prompt_versions:raw' => 1,
            'returning:raw' => 2,
        ]);
});

it('validates every qualified delete target component before normalization', function (): void {
    $base = dirname(__DIR__, 2);
    $root = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
        .DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'registry-ratchet-'.Str::uuid();
    mkdir($root, 0777, true);
    $fixture = <<<'PHP'
<?php

use Illuminate\Support\Facades\DB as Storage;

$suffix = getenv('REGISTRY_SUFFIX');

Storage::statement('DELETE FROM public.RETURNING ai_agent_versions');
Storage::statement('DELETE FROM public.Returning ai_agent_versions');
Storage::statement('DELETE FROM public.returning ai_agent_versions');
Storage::statement('DELETE FROM RETURNING.ai_agent_versions WHERE id = 1');
Storage::statement('DELETE FROM Returning.ai_agent_versions WHERE id = 1');
Storage::statement('DELETE FROM returning.ai_agent_versions WHERE id = 1');

Storage::statement('DELETE FROM public."returning" WHERE id = 1');
Storage::statement('DELETE FROM "returning".ai_agent_versions WHERE id = 1');
Storage::statement('DELETE FROM public.`returning` WHERE id = 1');
Storage::statement('DELETE FROM `returning`.ai_agent_versions WHERE id = 1');
Storage::statement('DELETE FROM public.[returning] WHERE id = 1');
Storage::statement('DELETE FROM [returning].ai_agent_versions WHERE id = 1');
Storage::statement('DELETE public.RETURNING FROM public.RETURNING JOIN orders ON orders.id = public.id');
Storage::statement('DELETE public."returning" FROM public."returning" JOIN orders ON orders.id = public.id');

Storage::statement('DELETE FROM public.ai_agent_versions RETURNING id');
Storage::statement('DELETE FROM public.ai_prompt_versions WHERE id = 1');
Storage::statement('DELETE FROM public.orders WHERE id = 1');

Storage::statement('DELETE FROM public.orders AS target USING ai_release_policies AS registry_source WHERE target.id = registry_source.id');
Storage::statement('DELETE orders FROM orders JOIN public.RETURNING AS registry_source ON registry_source.id = orders.id');
Storage::statement('DELETE FROM public.orders /* RETURNING ai_agent_versions */ WHERE id = 1');
Storage::statement("DELETE FROM public.orders WHERE note = 'RETURNING ai_prompt_versions'");
Storage::statement('DELETE FROM public.orders RETURNING ai_agent_versions');

Storage::statement("DELETE FROM public.ai_{$suffix} RETURNING id");
Storage::statement("DELETE FROM ai_{$suffix}.ai_agent_versions RETURNING id");
PHP;
    file_put_contents($root.DIRECTORY_SEPARATOR.'QualifiedDeleteTargets.php', $fixture);

    try {
        $findings = (new RegistryMutationScanner)->scan(
            [$root],
            [
                'returning',
                'ai_agent_versions',
                'ai_prompt_versions',
                'ai_release_policies',
            ],
        );
    } finally {
        unlink($root.DIRECTORY_SEPARATOR.'QualifiedDeleteTargets.php');
        rmdir($root);
    }

    expect(collect($findings)
        ->map(static fn (object $finding): string => $finding->table.':'.$finding->method)
        ->countBy()
        ->sortKeys()
        ->all())->toBe([
            '*dynamic*:raw' => 11,
            'ai_agent_versions:raw' => 3,
            'ai_prompt_versions:raw' => 1,
            'returning:raw' => 3,
        ]);
});

it('detects query aliases eloquent and raw registry mutation bypasses without false positives', function (): void {
    $base = dirname(__DIR__, 2);
    $root = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
        .DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'registry-ratchet-'.Str::uuid();
    mkdir($root, 0777, true);
    $modelFixture = <<<'PHP'
<?php

namespace Fixtures;

use Illuminate\Database\Eloquent\Model;

final class ProviderVersion extends Model
{
    protected $table = 'ai_provider_versions';
}
PHP;
    $fixture = <<<'PHP'
<?php

use Fixtures\ProviderVersion as RegistryProvider;
use Illuminate\Support\Facades\DB as Storage;

Storage::table('ai_agent_versions')->insertOrIgnore([]);
$activations = Storage::table('ai_agent_activations');
$activations->updateOrInsert(['id' => 'fixture'], []);
Storage::table('ai_release_decisions')->upsert([], ['id']);
Storage::table('ai_prompt_versions')->truncate();
Storage::unprepared('DELETE FROM ai_release_policies WHERE id = 1');
Storage::statement(Storage::raw('UPDATE ai_model_versions SET version = 2'));
Storage::insert('INSERT INTO ai_provider_versions (id) VALUES (?)', ['fixture']);
Storage::update('UPDATE ai_model_versions SET version = 3');
Storage::delete('DELETE FROM ai_prompt_versions WHERE id = ?', ['fixture']);
Storage::affectingStatement('UPDATE ai_release_policies SET status = ?', ['retired']);

RegistryProvider::query()->update([]);
RegistryProvider::create([]);
$provider = RegistryProvider::query()->first();
$provider->save();
$provider->delete();

$registry = Storage::table('ai_agent_versions');
$copy = $registry;
$copy->delete();
$registry = Storage::table('orders');
$registry->update(['status' => 'paid']);

// Storage::table('ai_agent_versions')->delete();
Storage::table('ai_agent_versions')->first();
Storage::table('orders')->update(['status' => 'paid']);
PHP;
    file_put_contents($root.DIRECTORY_SEPARATOR.'ProviderVersion.php', $modelFixture);
    file_put_contents($root.DIRECTORY_SEPARATOR.'Bypass.php', $fixture);

    try {
        $findings = (new RegistryMutationScanner)->scan(
            [$root],
            [
                'ai_provider_versions',
                'ai_model_versions',
                'ai_agent_versions',
                'ai_prompt_versions',
                'ai_release_policies',
                'ai_agent_activations',
                'ai_release_decisions',
            ],
        );
    } finally {
        unlink($root.DIRECTORY_SEPARATOR.'Bypass.php');
        unlink($root.DIRECTORY_SEPARATOR.'ProviderVersion.php');
        rmdir($root);
    }

    $pairs = collect($findings)
        ->map(static fn (object $finding): string => $finding->table.':'.$finding->method)
        ->sort()
        ->values()
        ->all();
    expect($pairs)->toBe([
        'ai_agent_activations:updateOrInsert',
        'ai_agent_versions:delete',
        'ai_agent_versions:insertOrIgnore',
        'ai_model_versions:raw',
        'ai_model_versions:update',
        'ai_prompt_versions:delete',
        'ai_prompt_versions:truncate',
        'ai_provider_versions:create',
        'ai_provider_versions:delete',
        'ai_provider_versions:insert',
        'ai_provider_versions:save',
        'ai_provider_versions:update',
        'ai_release_decisions:upsert',
        'ai_release_policies:affectingStatement',
        'ai_release_policies:raw',
    ]);
});

it('detects connection properties scopes quiet model writes and unresolved dynamic tables', function (): void {
    $base = dirname(__DIR__, 2);
    $root = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
        .DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'registry-ratchet-'.Str::uuid();
    mkdir($root, 0777, true);
    $modelFixture = <<<'PHP'
<?php

namespace Fixtures;

use Illuminate\Database\Eloquent\Model;

final class ProviderVersion extends Model
{
    protected $table = 'ai_provider_versions';
}
PHP;
    $fixture = <<<'PHP'
<?php

use Fixtures\ProviderVersion as RegistryProvider;
use Illuminate\Support\Facades\DB as Storage;

Storage::connection('registry')->table('ai_prompt_definitions')->delete();

$holder = new stdClass();
$holder->versions = Storage::table('ai_agent_versions');
$holder->versions->increment('version');
$holder->versions->decrement('version');

$captured = Storage::table('ai_model_versions');
(function () use ($captured): void {
    $captured->increment('version');
})();

$dynamic = getenv('REGISTRY_TABLE');
Storage::table($dynamic)->update(['updated_at' => now()]);

$provider = new RegistryProvider();
$provider->saveQuietly();
$provider->deleteQuietly();
$provider->forceDeleteQuietly();
$provider->incrementQuietly('version');
$provider->decrementQuietly('version');

$reassigned = Storage::table('ai_release_policies');
$reassigned = Storage::table('orders');
$reassigned->update(['status' => 'paid']);

function local_scope_isolated(): void
{
    $captured = Storage::table('orders');
    $captured->update(['status' => 'paid']);
}
PHP;
    file_put_contents($root.DIRECTORY_SEPARATOR.'ProviderVersion.php', $modelFixture);
    file_put_contents($root.DIRECTORY_SEPARATOR.'Origins.php', $fixture);

    try {
        $findings = (new RegistryMutationScanner)->scan(
            [$root],
            [
                'ai_provider_versions',
                'ai_model_versions',
                'ai_agent_versions',
                'ai_prompt_definitions',
                'ai_release_policies',
            ],
        );
    } finally {
        unlink($root.DIRECTORY_SEPARATOR.'Origins.php');
        unlink($root.DIRECTORY_SEPARATOR.'ProviderVersion.php');
        rmdir($root);
    }

    expect(collect($findings)
        ->map(static fn (object $finding): string => $finding->table.':'.$finding->method)
        ->sort()
        ->values()
        ->all())->toBe([
            '*dynamic*:update',
            'ai_agent_versions:decrement',
            'ai_agent_versions:increment',
            'ai_model_versions:increment',
            'ai_prompt_definitions:delete',
            'ai_provider_versions:decrementQuietly',
            'ai_provider_versions:deleteQuietly',
            'ai_provider_versions:forceDeleteQuietly',
            'ai_provider_versions:incrementQuietly',
            'ai_provider_versions:saveQuietly',
        ]);
});

it('keeps every canonical registry table and mutation method behind its exact owner', function (): void {
    $registryTables = [
        'ai_providers',
        'ai_provider_versions',
        'ai_models',
        'ai_model_versions',
        'ai_agent_definitions',
        'ai_agent_versions',
        'ai_prompt_definitions',
        'ai_prompt_versions',
        'ai_release_policies',
        'ai_agent_activations',
        'ai_release_decisions',
    ];
    $base = dirname(__DIR__, 2);
    $roots = [$base.'/app', $base.'/routes', $base.'/resources'];
    $expectedOwnership = [
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_agent_activations|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_agent_definitions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_agent_versions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_model_versions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_models|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_prompt_definitions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_prompt_versions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_provider_versions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_providers|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_providers|update' => 1,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_release_policies|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_agent_activations|insert' => 2,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_agent_activations|update' => 3,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_agent_versions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_agent_versions|update' => 6,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_model_versions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_model_versions|update' => 3,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_prompt_versions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_prompt_versions|update' => 3,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_provider_versions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_provider_versions|update' => 3,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_release_decisions|update' => 1,
        'app/Application/Ai/Registry/AiWorkforceDraftProvisioner.php|ai_agent_definitions|insert' => 1,
        'app/Application/Ai/Registry/AiWorkforceDraftProvisioner.php|ai_agent_versions|insert' => 1,
        'app/Application/Ai/Evaluation/AiReleaseGate.php|ai_release_decisions|insert' => 1,
    ];

    $actualOwnership = collect((new RegistryMutationScanner)->scan($roots, $registryTables))
        ->reject(static fn (object $finding): bool => $finding->table === '*dynamic*')
        ->map(static fn (object $finding): string => implode('|', [
            $finding->relativeFile,
            $finding->table,
            $finding->method,
        ]))
        ->countBy()
        ->sortKeys()
        ->all();
    ksort($expectedOwnership);

    expect($actualOwnership)->toBe($expectedOwnership);
});
