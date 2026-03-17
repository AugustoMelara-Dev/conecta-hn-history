<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Services\Upcasting;

use App\Domain\Shared\Contracts\UpcasterInterface;
use Illuminate\Support\Facades\Log;

class UpcasterChain
{
    /**
     * @var array<string, array<string, UpcasterInterface>>
     *                                                      [EventType => [SourceVersion => Upcaster]]
     */
    private array $upcasters = [];

    public function register(string $eventType, string $sourceVersion, UpcasterInterface $upcaster): void
    {
        $this->upcasters[$eventType][$sourceVersion] = $upcaster;
    }

    public function upcast(string $eventType, array $payload, string $currentVersion): array
    {
        $maxIterations = 100; // Circuit breaker for infinite loops
        $i = 0;

        while (isset($this->upcasters[$eventType][$currentVersion])) {
            if ($i++ > $maxIterations) {
                Log::error("Infinite upcasting loop detected for {$eventType} version {$currentVersion}");
                break;
            }

            $upcaster = $this->upcasters[$eventType][$currentVersion];

            // Apply transformation
            $payload = $upcaster->upcast($payload);

            // Advance version pointer
            $oldVersion = $currentVersion;
            $currentVersion = (string) $upcaster->toVersion();

            Log::info("Upcasted {$eventType} from {$oldVersion} to {$currentVersion}");
        }

        // Return latest payload (and we could return latest version too if needed)
        return $payload;
    }
}
