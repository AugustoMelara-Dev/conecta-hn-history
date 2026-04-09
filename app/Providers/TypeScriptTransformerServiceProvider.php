<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

if (class_exists(\Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider::class)) {
    class TypeScriptTransformerServiceProvider extends \Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider
    {
        protected function configure(\Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory $config): void
        {
            $config
                ->outputDirectory(resource_path('js/types'))
                ->transformer(\Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer::class)
                ->transformer(\Spatie\TypeScriptTransformer\Transformers\EnumTransformer::class)
                ->transformDirectories(app_path())
                ->writer(new \Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter('generated.d.ts'));
        }
    }
} else {
    class TypeScriptTransformerServiceProvider extends ServiceProvider {}
}
