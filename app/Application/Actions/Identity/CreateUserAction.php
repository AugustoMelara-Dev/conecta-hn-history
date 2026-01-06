<?php

declare(strict_types=1);

namespace App\Application\Actions\Identity;

use App\Domain\Identity\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * CreateUserAction
 * 
 * ARCHITECTURAL BOUNDARY:
 * This class belongs to the APPLICATION LAYER.
 * It is the ONLY authority allowed to orchestrate persistence for User creation.
 * 
 * "The Domain defines the state; The Application defines the persistence."
 */
final class CreateUserAction
{
    /**
     * Create a new user with transactional integrity.
     *
     * @param array $userData Validated data payload
     * @return User The persisted user entity
     */
    public function handle(array $userData): User
    {
        return DB::transaction(function () use ($userData) {
            
            // 1. Instantiation (Domain)
            // We use the model as an Entity. It has NO persistence state yet.
            $user = new User();
            
            // 2. Hydration & Logic (Domain)
            $user->name = $userData['name'];
            $user->email = $userData['email'];
            $user->password = Hash::make($userData['password']);
            
            // STRICT TYPING: Use Enum instead of magic string if available
            // Fallback to default customer role if not provided
            $user->role = isset($userData['role']) 
                ? (UserRole::tryFrom($userData['role']) ?? UserRole::CUSTOMER)
                : UserRole::CUSTOMER;
            
            // Example of Rich Domain Method call (Entity Logic)
            // $user->setInitialStatus(); 

            // 3. Persistence (Infrastructure)
            // Explicit save call managed by the Application Layer
            $user->save();
            
            Log::info("User created via Application Action", ['uuid' => $user->id]);

            return $user;
        });
    }
}
