<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Search\Contracts\GlobalSearchReader;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SearchController extends Controller
{
    public function __construct(
        private GlobalSearchReader $reader,
        private FilesystemFactory $storage
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $request->input('query');

        if (! is_string($query) || strlen($query) < 3) {
            return response()->json(['tenants' => []], 200);
        }

        $results = $this->reader->searchTenants(
            query: $query,
            citySlug: $request->input('city'),
            categorySlug: $request->input('category'),
            limit: 20
        );

        $tenants = collect($results)
            ->map(function (mixed $tenant): array {
                $logoPath = data_get($tenant, 'logo_path');
                $categoryName = data_get($tenant, 'category.name');
                $cityName = data_get($tenant, 'city.name');

                return [
                    'id' => data_get($tenant, 'id'),
                    'name' => data_get($tenant, 'name'),
                    'slug' => data_get($tenant, 'slug'),
                    'logo_url' => is_string($logoPath) && $logoPath !== ''
                        ? $this->storage->disk('public')->url($logoPath)
                        : null,
                    'category_name' => is_string($categoryName) ? $categoryName : null,
                    'city_name' => is_string($cityName) ? $cityName : null,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'tenants' => $tenants,
        ]);
    }
}
