<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Actions\Reviews\SubmitReviewAction;
use App\Application\Reviews\Results\SubmitReviewResult;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ReviewController - Public HTTP Gateway
 *
 * â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
 * â•‘  REFACTORED: The Dumb Gateway                                            â•‘
 * â• â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•£
 * â•‘  Responsibility:                                                         â•‘
 * â•‘  1. Validate Input (Framework Level)                                     â•‘
 * â•‘  2. Hydrate DTO                                                          â•‘
 * â•‘  3. Invoke Action                                                        â•‘
 * â•‘  4. Return Response                                                      â•‘
 * â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
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
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            // 2. Hydrate DTO
            $data = SubmitReviewResult::fromRequest($request);

            // 3. Invoke Action
            $action->execute($data);

            // 4. Return Standard Response
            return response()->json([
                'status' => 'success',
                'message' => 'ReseÃ±a enviada correctamente.',
            ], 201);

        } catch (\Exception $e) {
            // Log error internally, return generic message externally
            report($e);

            return response()->json([
                'status' => 'error',
                'message' => 'OcurriÃ³ un error al procesar tu reseÃ±a.',
            ], 500);
        }
    }
}
