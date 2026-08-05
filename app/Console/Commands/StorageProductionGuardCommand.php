<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class StorageProductionGuardCommand extends Command
{
    protected $signature = 'storage:production-guard
        {--env= : Environment to validate, defaults to APP_ENV}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Fail release when durable production storage is not configured safely.';

    public function handle(): int
    {
        $environment = (string) ($this->option('env') ?: app()->environment());
        $managedEnvironment = in_array($environment, ['production', 'staging'], true);
        $violations = [];

        if ($managedEnvironment) {
            foreach (['local', 'private', 'public', 'media'] as $disk) {
                $definition = config("filesystems.disks.{$disk}");
                $driver = is_array($definition) ? (string) ($definition['driver'] ?? '') : '';
                $bucket = is_array($definition) ? (string) ($definition['bucket'] ?? '') : '';
                $visibility = is_array($definition) ? (string) ($definition['visibility'] ?? '') : '';
                $throws = is_array($definition) && ($definition['throw'] ?? false) === true;
                $reports = is_array($definition) && ($definition['report'] ?? false) === true;

                if ($driver !== 's3') {
                    $violations[] = $this->issue(
                        strtoupper($disk).'_DISK_DRIVER',
                        $driver === '' ? 'missing' : $driver,
                        's3',
                        'ECS tasks have ephemeral filesystems; application objects must survive task replacement.',
                    );
                }

                if ($bucket === '') {
                    $violations[] = $this->issue(
                        strtoupper($disk).'_DISK_BUCKET',
                        'missing',
                        'configured',
                        'Every production object class needs an explicit S3 bucket.',
                    );
                }

                if ($visibility !== 'private') {
                    $violations[] = $this->issue(
                        strtoupper($disk).'_DISK_VISIBILITY',
                        $visibility === '' ? 'missing' : $visibility,
                        'private',
                        'S3 Block Public Access and CloudFront origin access are the only approved publication boundary.',
                    );
                }

                if (! $throws) {
                    $violations[] = $this->issue(
                        strtoupper($disk).'_DISK_THROW',
                        'false',
                        'true',
                        'Durability failures must abort the operation instead of being silently ignored.',
                    );
                }

                if (! $reports) {
                    $violations[] = $this->issue(
                        strtoupper($disk).'_DISK_REPORT',
                        'false',
                        'true',
                        'Object storage failures must be observable in the production error pipeline.',
                    );
                }
            }

            $defaultDisk = (string) config('filesystems.default');
            if (! in_array($defaultDisk, ['local', 'private'], true)) {
                $violations[] = $this->issue(
                    'FILESYSTEM_DISK',
                    $defaultDisk === '' ? 'missing' : $defaultDisk,
                    'local|private',
                    'The default disk is a private object boundary, never a public media disk.',
                );
            }

            foreach (['public', 'media'] as $disk) {
                $url = (string) config("filesystems.disks.{$disk}.url", '');
                if (! $this->isHttpsUrl($url)) {
                    $violations[] = $this->issue(
                        strtoupper($disk).'_DISK_URL',
                        $url === '' ? 'missing' : 'invalid or non-HTTPS',
                        'HTTPS CloudFront URL',
                        'Published derived media must be served through the approved encrypted CDN origin.',
                    );
                }
            }

            foreach (['local', 'private', 'public', 'media'] as $disk) {
                $endpoint = (string) config("filesystems.disks.{$disk}.endpoint", '');
                if ($endpoint !== '' && ! $this->isHttpsUrl($endpoint)) {
                    $violations[] = $this->issue(
                        strtoupper($disk).'_DISK_ENDPOINT',
                        'invalid or non-HTTPS',
                        'empty AWS endpoint or HTTPS-compatible endpoint',
                        'Object credentials and contents must be encrypted in transit.',
                    );
                }

                if ((bool) config("filesystems.disks.{$disk}.use_path_style_endpoint", false)) {
                    $violations[] = $this->issue(
                        strtoupper($disk).'_DISK_PATH_STYLE',
                        'true',
                        'false',
                        'The approved AWS S3 topology uses virtual-hosted endpoints.',
                    );
                }
            }
        }

        $payload = [
            'status' => $violations === [] ? 'ok' : 'failed',
            'environment' => $environment,
            'default_disk' => config('filesystems.default'),
            'violations' => $violations,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $violations === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($violations === []) {
            $this->info('Production storage guard passed.');

            return self::SUCCESS;
        }

        $this->error('Production storage guard failed.');
        foreach ($violations as $violation) {
            $this->line("- {$violation['key']}: {$violation['actual']} expected {$violation['expected']}. {$violation['reason']}");
        }

        return self::FAILURE;
    }

    private function isHttpsUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && (string) parse_url($url, PHP_URL_HOST) !== '';
    }

    /**
     * @return array{key: string, actual: string, expected: string, reason: string}
     */
    private function issue(string $key, string $actual, string $expected, string $reason): array
    {
        return compact('key', 'actual', 'expected', 'reason');
    }
}
