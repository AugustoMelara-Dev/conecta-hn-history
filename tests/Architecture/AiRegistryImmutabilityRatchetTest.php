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
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_agent_activations|update' => 1,
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
    ];

    $actualOwnership = collect((new RegistryMutationScanner)->scan($roots, $registryTables))
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
