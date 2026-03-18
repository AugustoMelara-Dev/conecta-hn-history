<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Search\Contracts\GlobalSearchReader;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SearchController - Unified Search API
 *
 * ARCHITECTURE:
 * - Decoupled from Eloquent (uses GlobalSearchReader)
 * - Pure HTTP responsibility (Validation, Transformation, Response)
 * - Final, Strict
 */
final class SearchController extends Controller
{
    public function __construct(
        private GlobalSearchReader $reader,
        private FilesystemFactory $storage
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $request->input('query');

        // Validation
        if (! $query || ! is_string($query) || strlen($query) < 3) {
            return response()->json(['tenants' => []], 200); // Return empty successfully
        }

        // Execution
        $results = $this->reader->searchTenants(
            query: $query,
            citySlug: $request->input('city'),
            categorySlug: $request->input('category'),
            limit: 20
        );

        // Transformation
        // Ideally this should be a Resource, but for simplicity/speed in this sweep keeping map.
        $tenants = $results->map(fn ($tenant) => [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'logo_url' => $tenant->logo_path ? $this->storage->disk('public')->url($tenant->logo_path) : null,
            'category_name' => $tenant->category?->name,
            'city_name' => $tenant->city?->name,
        ]);

        return response()->json([
            'tenants' => $tenants,
        ]);
    }
}
