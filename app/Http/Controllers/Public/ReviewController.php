<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Reviews\Commands\SubmitReviewCommand;
use App\Application\Reviews\Results\SubmitReviewResult;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Throwable;

final class ReviewController extends Controller
{
    public function store(Request $request, Dispatcher $bus): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        Gate::authorize('create', Review::class);

        $tenantId = (string) $request->input('tenant_id', '');
        $validated = $request->validate([
            'tenant_id' => [
                'required',
                'uuid',
                Rule::exists('tenants', 'id')
                    ->where('is_active', true)
                    ->whereIn('subscription_status', ['active', 'trial']),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('is_visible', true),
                Rule::unique('reviews', 'product_id')->where(
                    static fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('user_id', (string) $user->getKey())
                ),
            ],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = $bus->dispatchSync(new SubmitReviewCommand(
                tenantId: (string) $validated['tenant_id'],
                userId: (string) $user->getKey(),
                productId: (int) $validated['product_id'],
                rating: (int) $validated['rating'],
                comment: isset($validated['comment']) ? (string) $validated['comment'] : null,
            ));

            abort_unless($result instanceof SubmitReviewResult, 500);

            if ($request->header('X-Inertia')) {
                return back()
                    ->setStatusCode(303)
                    ->with('success', 'Tu reseña fue publicada.');
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Reseña enviada correctamente.',
                'data' => [
                    'id' => $result->id,
                    'moderation_status' => $result->status,
                ],
            ], 201);
        } catch (Throwable $exception) {
            report($exception);

            if ($request->header('X-Inertia')) {
                return back()
                    ->setStatusCode(303)
                    ->with('error', 'No pudimos publicar la reseña. Intenta nuevamente.');
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al procesar tu reseña.',
            ], 500);
        }
    }
}
