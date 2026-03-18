<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateSdkCommand extends Command
{
    protected $signature = 'schema:generate-sdk';

    protected $description = 'Generate TitaniumSdk.ts with Intelligent Caching & SLA Monitoring';

    public function handle(): int
    {
        $schemaPath = resource_path('schemas');
        $outputPath = resource_path('js/Infrastructure/Http/TitaniumSdk.ts');

        $files = File::files($schemaPath);
        $methods = [];

        $this->info('Building Intelligent Titanium SDK...');

        foreach ($files as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $content = json_decode(file_get_contents($file->getPathname()), true);

            if (! isset($content['_meta'])) {
                $this->warn("Skipping [{$file->getFilename()}] - Missing _meta block.");

                continue;
            }

            $meta = $content['_meta'];
            $interfaceName = $meta['title'] ?? Str::studly($file->getBasename('.json'));

            $methods[] = $this->generateMethod($interfaceName, $meta);
        }

        $sdkContent = $this->assembleSdk($methods);

        // Ensure directory exists
        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($outputPath, $sdkContent);

        $this->info("Titanium SDK generated at [{$outputPath}].");

        return Command::SUCCESS;
    }

    private function generateMethod(string $interfaceName, array $meta): string
    {
        $methodName = Str::camel($meta['title']);

        $endpoint = $meta['endpoint'];
        $httpMethod = strtoupper($meta['method']);

        // Schema-Driven Intelligence Extraction
        $ttl = isset($meta['cache_ttl']) ? (int) $meta['cache_ttl'] : null;
        $sla = isset($meta['sla_threshold']) ? (int) $meta['sla_threshold'] : null;

        // Configuration Object Construction
        $configParts = [];
        if ($ttl !== null) {
            $configParts[] = "ttl: {$ttl}";
        }
        if ($sla !== null) {
            $configParts[] = "sla: {$sla}";
        }

        $intelligenceConfig = empty($configParts)
            ? ''
            : ', { '.implode(', ', $configParts).' }';

        // Parse Parameters: /users/{id} -> ${id}
        preg_match_all('/\{(\w+)\}/', $endpoint, $matches);
        $params = $matches[1];

        $tsParams = [];
        $urlRes = $endpoint;

        foreach ($params as $param) {
            $tsParams[] = "{$param}: string | number";
            $urlRes = str_replace("{{$param}}", "\${{$param}}", $urlRes);
        }

        // Add payload param for POST/PUT
        if (in_array($httpMethod, ['POST', 'PUT', 'PATCH'])) {
            $tsParams[] = 'payload: any';
            $requestConfig = ', data: payload';
        } else {
            $requestConfig = '';
        }

        $paramString = implode(', ', $tsParams);

        return <<<TS
    public static async {$methodName}({$paramString}): Promise<Types.{$interfaceName}> {
        return this.adapter.execute<Types.{$interfaceName}>(
            { method: '{$httpMethod}', url: `{$urlRes}`{$requestConfig} }{$intelligenceConfig}
        );
    }
TS;
    }

    private function assembleSdk(array $methods): string
    {
        $methodsBlock = implode("\n\n", $methods);

        return <<<TS
/* AUTO-GENERATED FILE. DO NOT EDIT. */
import * as Types from '../../Types/Generated';
import { TitaniumHttpAdapter } from './TitaniumHttpAdapter';

export class TitaniumSdk {
    private static adapter = new TitaniumHttpAdapter();

{$methodsBlock}
}
TS;
    }
}
