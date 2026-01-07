<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Actions\Reviews\SubmitReviewAction;
use App\Application\DTOs\Reviews\SubmitReviewData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * ReviewController - Public HTTP Gateway
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  REFACTORED: The Dumb Gateway                                            ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  Responsibility:                                                         ║
 * ║  1. Validate Input (Framework Level)                                     ║
 * ║  2. Hydrate DTO                                                          ║
 * ║  3. Invoke Action                                                        ║
 * ║  4. Return Response                                                      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class ReviewController extends Controller
{
    /**
     * Submit a new review.
     */
    public function store(Request $request, SubmitReviewAction $action): JsonResponse
    {
        // 1. Framework Validation
        $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            // 2. Hydrate DTO
            $data = SubmitReviewData::fromRequest($request);

            // 3. Invoke Action
            $action->execute($data);

            // 4. Return Standard Response
            return response()->json([
                'status' => 'success',
                'message' => 'Reseña enviada correctamente.',
            ], 201);

        } catch (\Exception $e) {
            // Log error internally, return generic message externally
            report($e);
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al procesar tu reseña.',
            ], 500);
        }
    }
}
