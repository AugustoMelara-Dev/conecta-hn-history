<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Persistence;

use App\Application\Common\Interfaces\IdempotencyRepositoryInterface;
use App\Application\Identity\Results\RegisterUserResult;
use App\Infrastructure\Common\Models\IdempotencyKey;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

/**
 * EloquentIdempotencyRepository (Titanium Security)
 * 
 * - Checksum Guard (Identity Imposter Prevention)
 * - Non-Destructive
 * - JSON Persistence
 */
final readonly class EloquentIdempotencyRepository implements IdempotencyRepositoryInterface
{
    private const CURRENT_VERSION = 1;

    public function getResult(string $key, string $checksum): ?RegisterUserResult
    {
        $record = IdempotencyKey::find($key);

        if (!$record || !isset($record->response_payload)) {
            return null;
        }

        // CHECKSUM GUARD
        // Assuming 'checksum' column exists in DB. 
        // We compare strict equality.
        if ($record->checksum !== $checksum) {
            throw new InvalidArgumentException("Idempotency key reused with different payload.");
        }

        $envelope = $record->response_payload; 

        if (!isset($envelope['v']) || $envelope['v'] !== self::CURRENT_VERSION) {
            return null; 
        }

        return RegisterUserResult::fromArray($envelope['payload']);
    }

    public function storeResult(string $key, RegisterUserResult $result): void
    {
        $envelope = [
            'v' => self::CURRENT_VERSION,
            'payload' => $result->toArray()
        ];

        // Ensure we update. Using find+save to be model-agnostic regarding fillable
        $record = IdempotencyKey::find($key);
        if ($record) {
            $record->response_payload = $envelope;
            $record->save();
        } else {
            // Should not happen in normal flow as acquireLock creates it, 
            // but for safety:
            $record = new IdempotencyKey();
            $record->key = $key;
            $record->response_payload = $envelope;
            $record->created_at = now();
            $record->save();
        }
    }

    public function acquireLock(string $key, string $checksum, int $ttlSeconds): bool
    {
        try {
            // Bypass Mass Assignment to ensure checksum is saved even if model not updated yet
            $record = new IdempotencyKey();
            $record->key = $key;
            $record->checksum = $checksum;
            $record->created_at = now();
            // payload is null
            $record->save();
            
            return true;

        } catch (QueryException $e) {
            $errorCode = $e->errorInfo[1] ?? 0;
            
            if ($e->getCode() === '23000' || $errorCode === 1062) {
                
                $existing = IdempotencyKey::find($key);
                
                if ($existing) {
                    // CHECKSUM GUARD
                    if ($existing->checksum !== $checksum) {
                        throw new InvalidArgumentException("Idempotency key reused with different payload.");
                    }

                    // PRESERVATION & ZOMBIE CHECKS
                    if (!empty($existing->response_payload)) {
                        return false;
                    }

                    $expirationTime = $existing->created_at->addSeconds($ttlSeconds);
                    if (now()->greaterThan($expirationTime)) {
                        $existing->delete();
                        return $this->acquireLock($key, $checksum, $ttlSeconds);
                    }
                }
                
                return false;
            }

            throw $e;
        }
    }

    public function releaseLock(string $key): void
    {
        $record = IdempotencyKey::find($key);
        if ($record && empty($record->response_payload)) {
            $record->delete();
        }
    }
}
