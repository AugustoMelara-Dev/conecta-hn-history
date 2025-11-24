<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FollowController extends Controller
{
    public function toggle(Request $request, Tenant $tenant)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Toggle follow
        $user->following()->toggle($tenant->id);

        // Check if now following
        $isFollowing = $user->following()->where('tenant_id', $tenant->id)->exists();

        // Get updated count
        $followersCount = $tenant->followers()->count();

        return response()->json([
            'following' => $isFollowing,
            'followers_count' => $followersCount,
            'message' => $isFollowing ? 'Ahora sigues a este negocio' : 'Has dejado de seguir a este negocio',
        ]);
    }
}
