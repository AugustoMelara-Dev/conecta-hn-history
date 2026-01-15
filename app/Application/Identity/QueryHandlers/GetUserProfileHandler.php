<?php

declare(strict_types=1);

namespace App\Application\Identity\QueryHandlers;

use App\Application\Identity\Queries\GetUserProfileQuery;
use App\Application\Identity\ViewModels\UserProfileViewModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * GetUserProfileHandler - Fast Track Read (O(1))
 * 
 * Bypasses Domain Layer and Eloquent Models.
 * Reads directly from DB and projects to ViewModel.
 */
final readonly class GetUserProfileHandler
{
    public function handle(GetUserProfileQuery $query): UserProfileViewModel
    {
        // Refactored to LEFT JOIN to support Users without Tenants (Platform Admins / Partial States)
        $row = DB::table('users')
            ->leftJoin('tenants', 'users.tenant_id', '=', 'tenants.id')
            ->select([
                'users.id', 
                'users.name', 
                'users.email', 
                'users.created_at',
                'tenants.name as tenant_name',
                'tenants.slug as tenant_slug',
            ])
            ->where('users.id', $query->userId)
            ->first();

        // 404 Guard Clause - Only if USER itself is missing
        if (!$row) {
            throw new ModelNotFoundException("User not found via Fast Track.");
        }

        // Project to ViewModel
        return UserProfileViewModel::fromRow($row);
    }
}
