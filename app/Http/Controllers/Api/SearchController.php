<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('query');

        if (!$query || strlen($query) < 2) {
            return response()->json(['tenants' => [], 'products' => []]);
        }

        $tenants = Tenant::where('name', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->orWhereHas('category', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->take(5)
            ->get(['id', 'name', 'slug', 'logo_path', 'category_id']);

        // Load category name for display
        $tenants->load('category:id,name');

        $products = Product::where('name', 'like', "%{$query}%")
            ->where('is_visible', true)
            ->take(5)
            ->with('tenant:id,name,slug')
            ->get(['id', 'tenant_id', 'name', 'image_path', 'price']);

        return response()->json([
            'tenants' => $tenants,
            'products' => $products,
        ]);
    }
}
