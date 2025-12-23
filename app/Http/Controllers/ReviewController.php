<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        // Check policy
        if ($request->user()->cannot('create', [Review::class, $product])) {
            abort(403, 'No puedes reseñar tu propio producto.');
        }

        // Check if already reviewed
        if ($request->user()->reviews()->where('product_id', $product->id)->exists()) {
            return back()->withErrors(['comment' => 'Ya has reseñado este producto.']);
        }

        $request->user()->reviews()->create($validated);

        return back();
    }
}
