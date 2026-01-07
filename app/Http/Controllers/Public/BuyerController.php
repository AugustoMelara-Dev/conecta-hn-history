<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class BuyerController extends Controller
{
    public function index()
    {
        $favorites = auth()->user()->favorites()->with(['product.tenant', 'product.category'])->get();
        $followedTenants = auth()->user()->following()->get();

        return Inertia::render('Buyer/Profile', [
            'favorites' => $favorites,
            'followedTenants' => $followedTenants,
        ]);
    }
}
