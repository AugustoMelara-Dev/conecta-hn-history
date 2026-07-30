<?php

declare(strict_types=1);

use App\Application\Ai\Registry\RegistryMutationScanner;
use Illuminate\Support\Str;

it('detects query aliases eloquent and raw registry mutation bypasses without false positives', function (): void {
    $base = dirname(__DIR__, 2);
    $root = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
        .DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'registry-ratchet-'.Str::uuid();
    mkdir($root, 0777, true);
    $fixture = <<<'PHP'
<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class ProviderVersion extends Model
{
    protected $table = 'ai_provider_versions';
}

DB::table('ai_agent_versions')->insertOrIgnore([]);
$activations = DB::table('ai_agent_activations');
$activations->updateOrInsert(['id' => 'fixture'], []);
DB::table('ai_release_decisions')->upsert([], ['id']);
DB::table('ai_prompt_versions')->truncate();
DB::unprepared('DELETE FROM ai_release_policies WHERE id = 1');
DB::statement(DB::raw('UPDATE ai_model_versions SET version = 2'));
ProviderVersion::create([]);
$provider = new ProviderVersion();
$provider->save();
$provider->delete();

DB::table('ai_agent_versions')->first();
DB::table('orders')->update(['status' => 'paid']);
PHP;
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
        rmdir($root);
    }

    $pairs = collect($findings)
        ->map(static fn (object $finding): string => $finding->table.':'.$finding->method)
        ->sort()
        ->values()
        ->all();
    expect($pairs)->toBe([
        'ai_agent_activations:updateOrInsert',
        'ai_agent_versions:insertOrIgnore',
        'ai_model_versions:raw',
        'ai_prompt_versions:truncate',
        'ai_provider_versions:create',
        'ai_provider_versions:delete',
        'ai_provider_versions:save',
        'ai_release_decisions:upsert',
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
    $allowedOwnership = [
        'app/Application/Ai/Registry/AiRegistryBackfill.php' => [
            'ai_providers' => ['insert', 'update'],
            'ai_provider_versions' => ['insert'],
            'ai_models' => ['insert'],
            'ai_model_versions' => ['insert'],
            'ai_agent_definitions' => ['insert'],
            'ai_agent_versions' => ['insert'],
            'ai_prompt_definitions' => ['insert'],
            'ai_prompt_versions' => ['insert'],
            'ai_release_policies' => ['insert'],
            'ai_agent_activations' => ['insert', 'update'],
        ],
        'app/Application/Ai/Registry/AiRegistryLifecycle.php' => [
            'ai_providers' => ['insert', 'update'],
            'ai_provider_versions' => ['insert', 'update'],
            'ai_models' => ['insert', 'update'],
            'ai_model_versions' => ['insert', 'update'],
            'ai_agent_definitions' => ['insert', 'update'],
            'ai_agent_versions' => ['insert', 'update'],
            'ai_prompt_definitions' => ['insert', 'update'],
            'ai_prompt_versions' => ['insert', 'update'],
            'ai_agent_activations' => ['insert', 'update'],
            'ai_release_decisions' => ['insert', 'update'],
        ],
    ];

    $unauthorized = collect((new RegistryMutationScanner)->scan($roots, $registryTables))
        ->reject(static fn (object $finding): bool => in_array(
            $finding->method,
            $allowedOwnership[$finding->relativeFile][$finding->table] ?? [],
            true,
        ))
        ->map(static fn (object $finding): string => implode(':', [
            $finding->relativeFile,
            $finding->table,
            $finding->method,
            $finding->line,
        ]))
        ->values()
        ->all();

    expect($unauthorized)->toBe([]);
});
