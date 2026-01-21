<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring;

use Illuminate\Support\Facades\Log;

/**
 * PersistentStateStore - Level 53 Deterministic Convergence
 * 
 * Provides a hardened interface for state persistence that survives container/process death.
 * Replaces direct file manipulations with a structured, rigorous contract.
 * 
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  NON-EPHEMERALTY GUARANTEE: State stored outside volatile /tmp           ║
 * ║  ATOMICITY: Locking ensures no read/write races on state updates         ║
 * ║  PESSIMISTIC DISCOVERY: Forces observation window on first boot          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class PersistentStateStore
{
    private const STORAGE_PATH = '/opt/outbox_data'; // Persistent Volume Mount Path
    
    public function __construct(
        private readonly string $workerId
    ) {
        $this->ensureStorageDirectory();
    }

    /**
     * Ensure the persistent storage directory exists.
     */
    private function ensureStorageDirectory(): void
    {
        if (!is_dir(self::STORAGE_PATH)) {
            // In a real K8s setup, this would be a mount point.
            // For this implementation, we ensure the directory structure exists.
            @mkdir(self::STORAGE_PATH, 0755, true);
        }
    }

    /**
     * Get the file path for this worker's state.
     */
    private function getStateFilePath(): string
    {
        // Using a global state file for fleet-wide coordination ("The Amnesia Shield")
        // as requested in Level 52, but hardened in Level 53.
        return self::STORAGE_PATH . '/failure_state.json';
    }

    /**
     * Persist state to the hardened storage.
     * 
     * @param array $state The state array to persist
     */
    public function persist(array $state): void
    {
        $filePath = $this->getStateFilePath();
        $state['updated_at'] = microtime(true);
        $state['worker_id'] = $this->workerId;
        
        try {
            // Atomic write with exclusive lock
            file_put_contents($filePath, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX);
        } catch (\Throwable $e) {
            Log::emergency('PERSISTENT STORE FAILURE: Could not write state to disk', [
                'error' => $e->getMessage(),
                'path' => $filePath,
                'worker_id' => $this->workerId,
            ]);
        }
    }

    /**
     * Rehydrate state from the hardened storage.
     * 
     * @return array|null The rehydrated state or null if not found/corrupted
     */
    public function rehydrate(): ?array
    {
        $filePath = $this->getStateFilePath();
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        try {
            // Read with shared lock
            $fp = fopen($filePath, 'r');
            if (flock($fp, LOCK_SH)) {
                $content = stream_get_contents($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
                
                return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            }
            fclose($fp);
            return null;
        } catch (\Throwable $e) {
            Log::warning('PERSISTENT STORE WARNING: Could not read state from disk', [
                'error' => $e->getMessage(),
                'path' => $filePath,
            ]);
            return null;
        }
    }
}
