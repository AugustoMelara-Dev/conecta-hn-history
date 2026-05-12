<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Directory\DirectoryGuideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DirectoryGuideController extends Controller
{
    public function __construct(
        private readonly DirectoryGuideService $guide
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:240'],
            'budget' => ['nullable', 'numeric', 'min:1', 'max:100000'],
            'city' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:80'],
        ]);

        return response()->json($this->guide->answer(
            message: $validated['message'],
            budget: isset($validated['budget']) ? (float) $validated['budget'] : null,
            city: $validated['city'] ?? null,
            category: $validated['category'] ?? null,
        ));
    }
}
