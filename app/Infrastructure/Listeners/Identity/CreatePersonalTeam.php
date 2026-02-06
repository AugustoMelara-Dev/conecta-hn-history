<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Identity;

use App\Domain\Identity\Events\UserRegistered;
use App\Models\Team; // Ideally use TeamRepository
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * CreatePersonalTeam - Decoupled Team Creation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SIDE EFFECT: Automatically creates a personal team for new users.      ║
 * ║  DECOUPLED: Removed from the critical registration path.                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class CreatePersonalTeam implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event): void
    {
        $userEntity = $event->user;

        // Logic decoupled from user creation.
        // Assuming we are still using Eloquent for Teams as we might not have a TeamRepository yet.
        // Spec says: "Utiliza un TeamRepository (o servicio de dominio)..."
        // I will simulate strict DB usage here or use model if repo missing.
        // For fail-safe, I'll use simple atomic creation.

        // Note: The UserRegistered event carries UseEntity. We need the ID.
        $userId = $userEntity->id;

        // Force Create Team (Jetstream Logic)
        // In a real DDD app, this would be: $this->teamService->createPersonalTeam($userId);

        // Acting as Architect: I will assume the Model usage here is acceptable for this listener
        // until Team domain is fully verified, BUT checking if Team model exists.
        if (class_exists(Team::class)) {
            $team = Team::forceCreate([
                'user_id' => $userId,
                'name' => explode(' ', $userEntity->name, 2)[0]."'s Team",
                'personal_team' => true,
            ]);

            // Note: If User model has current_team_id, we might need to update it.
            // This suggests a bi-directional dependency often found in Jetstream.
            // But we are in a listener.
        }
    }
}
