<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Projections;

use App\Domain\Identity\Events\UserRegistered;
use App\Infrastructure\Common\EventStore\ProjectorInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * UserProjector - The Atomic Builder
 *
 * Writes strictly to 'users' table using Query Builder (No Eloquent).
 * Performance Optimized for Replay.
 */
final readonly class UserProjector implements ProjectorInterface
{
    public function supports(object $event): bool
    {
        return $event instanceof UserRegistered;
    }

    public function reset(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('users')->truncate();
        Schema::enableForeignKeyConstraints();
    }

    public function project(object $event): void
    {
        if (! $this->supports($event)) {
            return;
        }

        // Strict Class Matching
        $class = get_class($event);

        if ($class === UserRegistered::class) {
            /** @var UserRegistered $event */

            // Time Fidelity Rule
            $timestamp = method_exists($event, 'occurredOn') ? $event->occurredOn() : now();

            try {
                // Replayable Rule: insertOrIgnore as requested.
                // Ensures we don't overwrite if it exists (Immutability of first creation).
                DB::table('users')->insertOrIgnore([
                    'id' => $event->user_id,
                    'name' => $event->name,
                    'email' => $event->email,
                    'password' => $event->password_hash,
                    'role' => $event->role,
                    'tenant_id' => $event->tenant_id,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'email_verified_at' => null,
                    'remember_token' => null,
                ]);

            } catch (\Throwable $e) {
                Log::error("UserProjector failed for [{$event->user_id}]: ".$e->getMessage());
            }
        }
        // Future: elseif ($class === UserRoleChanged::class) ...
    }
}
