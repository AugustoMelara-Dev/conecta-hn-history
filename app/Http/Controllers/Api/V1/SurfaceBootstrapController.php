<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Surface\SurfaceSplitBootstrapAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SurfaceBootstrapController extends Controller
{
    public function __construct(
        private readonly SurfaceSplitBootstrapAdapter $bootstrapAdapter
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'error_code' => 'AUTH_REQUIRED',
                'message' => 'Debes iniciar sesion para obtener el bootstrap de superficies.',
            ], 401);
        }

        return response()->json([
            'data' => $this->bootstrapAdapter->forUser($user, $request),
            'meta' => [
                'version' => 'surface-bootstrap-v1',
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
