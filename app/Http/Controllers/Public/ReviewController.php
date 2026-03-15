<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Actions\Reviews\SubmitReviewAction;
use App\Application\Reviews\Results\SubmitReviewResult;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * ReviewController - Public HTTP Gateway
 *
 * Responsibility:
 * 1. Validate input
 * 2. Hydrate DTO
 * 3. Invoke action
 * 4. Return redirect response for Inertia
 */
final class ReviewController extends Controller
{
    /**
     * Submit a new review.
     */
    public function store(Request $request, SubmitReviewAction $action): RedirectResponse
    {
        $request->validate([
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $data = SubmitReviewResult::fromRequest($request);
            $action->execute($data);

            return back()
                ->setStatusCode(303)
                ->with('success', 'Resena enviada y pendiente de moderacion. Gracias!');
        } catch (\Exception $e) {
            report($e);

            return back()
                ->setStatusCode(303)
                ->withErrors(['error' => 'Ocurrio un error al procesar tu resena.']);
        }
    }
}
