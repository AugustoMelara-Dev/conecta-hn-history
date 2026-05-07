<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class StorageSmokeCommand extends Command
{
    protected $signature = 'storage:smoke
        {--disk= : Filesystem disk to smoke, defaults to FILESYSTEM_DISK}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Upload, read and delete a small probe file on the configured storage disk.';

    public function handle(): int
    {
        $disk = (string) ($this->option('disk') ?: config('filesystems.default'));
        $path = 'smoke/'.now()->format('YmdHis').'-'.Str::lower(Str::random(10)).'.txt';
        $contents = 'conecta-hn-storage-smoke:'.Str::random(24);

        $payload = [
            'status' => 'failed',
            'disk' => $disk,
            'path' => $path,
            'steps' => [],
            'error' => null,
        ];

        try {
            Storage::disk($disk)->put($path, $contents);
            $payload['steps']['put'] = true;

            $read = Storage::disk($disk)->get($path);
            $payload['steps']['read'] = hash_equals($contents, (string) $read);

            Storage::disk($disk)->delete($path);
            $payload['steps']['delete'] = ! Storage::disk($disk)->exists($path);

            $payload['status'] = in_array(false, $payload['steps'], true) ? 'failed' : 'ok';
        } catch (Throwable $exception) {
            $payload['error'] = $exception->getMessage();

            try {
                Storage::disk($disk)->delete($path);
            } catch (Throwable) {
                // Best-effort cleanup; the original failure is the useful signal.
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($payload['status'] === 'ok') {
            $this->info("Storage smoke passed for disk [{$disk}].");
        } else {
            $this->error("Storage smoke failed for disk [{$disk}].");

            if (is_string($payload['error'])) {
                $this->line($payload['error']);
            }
        }

        return $payload['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}
