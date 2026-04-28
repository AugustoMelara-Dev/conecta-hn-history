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
        {--disk= : Disk to test, defaults to filesystems.default}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Verify configured storage can upload, read and delete a small smoke file.';

    public function handle(): int
    {
        $diskName = (string) ($this->option('disk') ?: config('filesystems.default', 'local'));
        $path = 'smoke/storage-smoke-'.Str::uuid().'.txt';
        $body = 'conecta-hn-storage-smoke:'.Str::uuid();
        $deleted = false;

        try {
            $disk = Storage::disk($diskName);
            $written = $disk->put($path, $body);
            $read = $disk->get($path);
            $deleted = $disk->delete($path);

            $payload = [
                'status' => $written && $read === $body && $deleted ? 'ok' : 'failed',
                'disk' => $diskName,
                'path' => $path,
                'write' => (bool) $written,
                'read' => $read === $body,
                'delete' => (bool) $deleted,
            ];
        } catch (Throwable $exception) {
            if (! $deleted) {
                rescue(fn () => Storage::disk($diskName)->delete($path), report: false);
            }

            $payload = [
                'status' => 'failed',
                'disk' => $diskName,
                'path' => $path,
                'write' => false,
                'read' => false,
                'delete' => false,
                'error' => $exception::class,
                'message' => $exception->getMessage(),
            ];
        }

        $this->renderPayload($payload);

        return $payload['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderPayload(array $payload): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        if ($payload['status'] === 'ok') {
            $this->info("Storage smoke passed on disk [{$payload['disk']}].");

            return;
        }

        $this->error("Storage smoke failed on disk [{$payload['disk']}].");
        if (isset($payload['message'])) {
            $this->line((string) $payload['message']);
        }
    }
}
