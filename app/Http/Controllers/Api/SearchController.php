<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Contracts\SearchRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * SearchController - Unified Search API
 *
 * ARCHITECTURE:
 * - Decoupled from Eloquent (uses SearchRepository)
 * - Pure HTTP responsibility (Validation, Transformation, Response)
 */
class SearchController extends Controller
{
    public function __construct(
        private readonly SearchRepository $searchRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $request->input('query');

        // Validation
        if (! $query || ! is_string($query) || strlen($query) < 3) {
            return response()->json(['tenants' => []], 200); // Return empty successfully
        }

        // Execution
        $results = $this->searchRepository->searchTenants(
            query: $query,
            citySlug: $request->input('city'),
            categorySlug: $request->input('category'),
            limit: 20
        );

        // Transformation
        $tenants = $results->map(fn ($tenant) => [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'logo_url' => $tenant->logo_path ? Storage::url($tenant->logo_path) : null,
            'category_name' => $tenant->category?->name,
            'city_name' => $tenant->city?->name,
        ]);

        return response()->json([
            'tenants' => $tenants,
        ]);
    }
}
