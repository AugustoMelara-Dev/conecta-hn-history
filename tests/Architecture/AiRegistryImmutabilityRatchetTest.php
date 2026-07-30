<?php

declare(strict_types=1);

use App\Application\Ai\Registry\RegistryMutationScanner;
use Illuminate\Support\Str;

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
    $roots = [
        $base.'/app/Application',
        $base.'/app/Domain',
        $base.'/app/Http',
        $base.'/app/Infrastructure',
        $base.'/app/Jobs',
        $base.'/resources',
    ];
    $expectedOwnership = [
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_agent_activations|insert' => 2,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_agent_activations|update' => 1,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_agent_definitions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_agent_versions|insert' => 2,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_model_versions|insert' => 2,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_models|insert' => 2,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_prompt_definitions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_prompt_versions|insert' => 2,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_provider_versions|insert' => 2,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_providers|insert' => 2,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_providers|update' => 1,
        'app/Application/Ai/Registry/AiRegistryBackfill.php|ai_release_policies|insert' => 2,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_agent_activations|insert' => 3,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_agent_activations|update' => 5,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_agent_definitions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_agent_definitions|update' => 3,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_agent_versions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_agent_versions|update' => 6,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_model_versions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_model_versions|update' => 3,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_models|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_models|update' => 3,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_prompt_definitions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_prompt_definitions|update' => 3,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_prompt_versions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_prompt_versions|update' => 3,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_provider_versions|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_provider_versions|update' => 3,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_providers|insert' => 1,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php|ai_providers|update' => 3,
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
