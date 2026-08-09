<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

const BUSINESS_COMPONENTS_CSS = 'resources/css/filament/app/business-components.css';
const REPOSITORY_ROOT = __DIR__.'/../..';

it('loads the native business component stylesheet only in the app panel', function (): void {
    $provider = file_get_contents(REPOSITORY_ROOT.'/app/Providers/Filament/AppPanelProvider.php');
    $adminProvider = file_get_contents(REPOSITORY_ROOT.'/app/Providers/Filament/AdminPanelProvider.php');
    $vite = file_get_contents(REPOSITORY_ROOT.'/vite.config.js');

    assertAppBusinessComponentsAssetRegistration($provider);

    expect($provider)
        ->not->toContain('->viteTheme(')
        ->and($adminProvider)
        ->not->toContain('app-business-components')
        ->not->toContain(BUSINESS_COMPONENTS_CSS)
        ->and($vite)->toContain('"'.BUSINESS_COMPONENTS_CSS.'"');
});

it('rejects registrations whose apparent source path is only bait', function (): void {
    $oneArgument = <<<'PHP'
<?php

use Filament\Support\Assets\Css;
use Illuminate\Support\Facades\Vite;

final class AppPanelProvider
{
    public function panel($panel)
    {
        // Css::make('app-business-components', resource_path('css/filament/app/business-components.css'));
        $bait = "Vite::asset('resources/css/filament/app/business-components.css')";

        return $panel->assets([
            Css::make('app-business-components')
                ->html(fn () => Vite::asset('resources/css/filament/app/business-components.css')),
        ]);
    }
}
PHP;

    expect(fn () => assertAppBusinessComponentsAssetRegistration($oneArgument))
        ->toThrow(RuntimeException::class, 'must receive exactly two arguments');

    $wrongSource = str_replace(
        "Css::make('app-business-components')",
        "Css::make('app-business-components', resource_path('css/filament/app/not-the-business-components.css'))",
        $oneArgument,
    );

    expect(fn () => assertAppBusinessComponentsAssetRegistration($wrongSource))
        ->toThrow(RuntimeException::class, 'must use the canonical publishable source path');
});

it('keeps the panel extension native and independent from the tailwind toolchain', function (): void {
    $path = REPOSITORY_ROOT.'/'.BUSINESS_COMPONENTS_CSS;

    expect(is_file($path))->toBeTrue();

    $css = file_get_contents($path);

    expect($css)
        ->not->toContain('@tailwind')
        ->not->toContain('@apply')
        ->not->toContain('@import');
});

it('defines every business component token used by app panel blade views', function (): void {
    $path = REPOSITORY_ROOT.'/'.BUSINESS_COMPONENTS_CSS;

    expect(is_file($path))->toBeTrue();

    $css = file_get_contents($path);
    $tokens = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(REPOSITORY_ROOT.'/resources/views/filament/app', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! Str::endsWith($file->getFilename(), '.blade.php')) {
            continue;
        }

        preg_match_all('/\bbiz-[a-z0-9-]+\b/', file_get_contents($file->getPathname()), $matches);
        array_push($tokens, ...$matches[0]);
    }

    $externalAppViews = [
        REPOSITORY_ROOT.'/resources/views/filament/widgets/free-plan-upsell.blade.php',
    ];

    foreach ($externalAppViews as $view) {
        expect(is_file($view))->toBeTrue();
        preg_match_all('/\bbiz-[a-z0-9-]+\b/', file_get_contents($view), $matches);
        array_push($tokens, ...$matches[0]);
    }

    $tokens = array_values(array_unique($tokens));
    sort($tokens);

    expect($tokens)->not->toBeEmpty();

    foreach ($tokens as $token) {
        expect($css)->toMatch('/\\.'.preg_quote($token, '/').'(?=[\\s,{.:>])/');
    }
});

function assertAppBusinessComponentsAssetRegistration(string $source): void
{
    $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];
    $finder = new NodeFinder;
    $makeCalls = array_values(array_filter(
        $finder->findInstanceOf($statements, StaticCall::class),
        static fn (StaticCall $call): bool => $call->class instanceof Name
            && $call->class->getLast() === 'Css'
            && $call->name instanceof Node\Identifier
            && $call->name->toString() === 'make',
    ));

    if (count($makeCalls) !== 1) {
        throw new RuntimeException('The App business stylesheet must have exactly one real Css::make registration.');
    }

    $make = $makeCalls[0];

    if (count($make->args) !== 2) {
        throw new RuntimeException('Css::make for the App business stylesheet must receive exactly two arguments.');
    }

    if (! $make->args[0]->value instanceof String_ || $make->args[0]->value->value !== 'app-business-components') {
        throw new RuntimeException('Css::make must use the canonical App business stylesheet id.');
    }

    $sourcePath = $make->args[1]->value;

    if (! $sourcePath instanceof FuncCall
        || ! $sourcePath->name instanceof Name
        || $sourcePath->name->toString() !== 'resource_path'
        || count($sourcePath->args) !== 1
        || ! $sourcePath->args[0]->value instanceof String_
        || $sourcePath->args[0]->value->value !== 'css/filament/app/business-components.css') {
        throw new RuntimeException('Css::make must use the canonical publishable source path.');
    }

    $htmlCalls = array_values(array_filter(
        $finder->findInstanceOf($statements, MethodCall::class),
        static fn (MethodCall $call): bool => $call->var === $make
            && $call->name instanceof Node\Identifier
            && $call->name->toString() === 'html',
    ));

    if (count($htmlCalls) !== 1 || count($htmlCalls[0]->args) !== 1) {
        throw new RuntimeException('The App business stylesheet must have exactly one html renderer.');
    }

    $renderer = $htmlCalls[0]->args[0]->value;
    $viteAsset = $renderer instanceof ArrowFunction ? $renderer->expr : null;

    if (! $viteAsset instanceof StaticCall
        || ! $viteAsset->class instanceof Name
        || $viteAsset->class->getLast() !== 'Vite'
        || ! $viteAsset->name instanceof Node\Identifier
        || $viteAsset->name->toString() !== 'asset'
        || count($viteAsset->args) !== 1
        || ! $viteAsset->args[0]->value instanceof String_
        || $viteAsset->args[0]->value->value !== BUSINESS_COMPONENTS_CSS) {
        throw new RuntimeException('The App business stylesheet html renderer must use the canonical Vite asset.');
    }
}
