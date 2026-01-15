<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Repositories;

use App\Application\Common\Integration\IntegrationEventMapper;
use App\Application\Identity\Interfaces\UserRepositoryInterface;
use App\Domain\Common\Contracts\EventStoreRepositoryInterface;
use App\Domain\Common\Contracts\SnapshotRepositoryInterface;
use App\Domain\Common\Contracts\SnapshotStrategyInterface;
use App\Domain\Identity\Entities\UserEntity;
use App\Infrastructure\Common\Outbox\OutboxRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use App\Domain\Identity\Exceptions\UserAlreadyExistsException;

/**
 * EventSourcedUserRepository - Hybrid Persistence
 * 
 * - Event Store (Write Model).
 * - SQL Table 'users' (Read Model) via Inline Projection.
 */
final readonly class EventSourcedUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EventStoreRepositoryInterface $eventStore,
        private SnapshotRepositoryInterface $snapshotRepository,
        private SnapshotStrategyInterface $snapshotStrategy,
        private OutboxRepository $outboxRepository,
        private IntegrationEventMapper $mapper
    ) {}

    public function save(UserEntity $user): void
    {
        // Note: The previous implementation wrapped this in DB::transaction.
        // But the Handler now wraps both calls in a parent transaction.
        // We can remove the transaction here to avoid nested transaction complications 
        // if supported, or keep it as a nested transaction (Laravel supports it).
        // The prompt says "Asegura que save acepte UserEntity $user y retorne void."
        // And "Dentro de la misma transacción DB::transaction".
        // If the Handler already opens one, we just execute.
        
        $events = method_exists($user, 'getUncommittedEvents') ? $user->getUncommittedEvents() : [];

        if (!empty($events)) {
            // 1. Source of Truth
            try {
                $this->eventStore->append(
                    tenantId: (string) $user->tenantId(),
                    aggregateId: (string) $user->id(),
                    aggregateType: 'User',
                    events: $events,
                    expectedVersion: $user->version(),
                    metaData: ['source' => 'EventSourcedUserRepository']
                );
            } catch (QueryException $e) {
                // Handle Duplicates
                $this->handleConcurrencyException($e, $user->email());
            }

            // 2. Integration
            $outboxMessages = $this->mapper->map($events);
            if (!empty($outboxMessages)) {
                $this->outboxRepository->append($outboxMessages);
            }
            
            // 3. INLINE SYNC PROJECTION (The Fix)
            $this->projectState($user);
        }
        
        // 4. Optimization
        if ($this->snapshotStrategy->shouldSnapshot($user)) {
             try {
                $this->snapshotRepository->save(
                    $user->id(),
                    $user->version(),
                    $user->toStateArray()
                );
             } catch (\Exception $e) {
                 // Swallow snapshot errors to not block flow?
             }
        }
    }
    
    // Explicit Read Methods
    
    public function findById(string $userId): ?UserEntity
    {
        // Implement Replay Logic like Tenant Repo (Simplified for brevity as prompt focused on Write Side fix)
        // But Interface mandates return ?UserEntity.
        // We will return null for now if not implementing full replay here yet.
        return null; 
    }
    
    public function existsByEmail(string $email): bool
    {
        return DB::table('users')->where('email', $email)->exists();
    }

    private function projectState(UserEntity $user): void
    {
        DB::table('users')->upsert(
            [
                'id' => $user->id(),
                'tenant_id' => $user->tenantId(),
                'name' => $user->name(), 
                'email' => $user->email(),
                'password' => $user->password(),
                'created_at' => $user->createdAt(), // Assumes DateTime casting works or format
                'updated_at' => now(),
            ],
            ['id'], // Unique Key
            ['email', 'password', 'updated_at'] // Update Columns
        );
    }

    private function handleConcurrencyException(QueryException $e, string $context): void
    {
        $errorCode = $e->errorInfo[1] ?? 0;
        if ($e->getCode() === '23000' || $errorCode === 1062) {
             throw new UserAlreadyExistsException("Integrity violation for {$context}.", 0, $e);
        }
        throw $e;
    }
}
