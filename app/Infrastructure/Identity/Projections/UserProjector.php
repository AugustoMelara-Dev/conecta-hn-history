<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Projections;

use App\Domain\Identity\Events\UserRegistered;
use App\Infrastructure\Common\EventStore\ProjectorInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * UserProjector - The Atomic Builder
 *
 * Writes strictly to 'users' table using Query Builder (No Eloquent).
 * Performance Optimized for Replay.
 */
final readonly class UserProjector implements ProjectorInterface
{
    private const PROJECTION_PASSWORD_PLACEHOLDER = '$2y$10$iglSI7mJLN.7h6wHHik4v.oBApN1Th82ZeeiLfDn5mAxghs66P6My';

    public function supports(object $event): bool
    {
        return $event instanceof UserRegistered;
    }

    public function reset(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        DB::table('users')->truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
    }

    public function project(object $event): void
    {
        if (! $event instanceof UserRegistered) {
            return;
        }

        $timestamp = $event->occurredOn();

        try {
            DB::table('users')->insertOrIgnore([
                'id' => $event->user_id,
                'name' => $event->name,
                'email' => Crypt::decryptString($event->email->value()),
                'password' => self::PROJECTION_PASSWORD_PLACEHOLDER,
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
}
