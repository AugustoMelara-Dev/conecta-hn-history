<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BuyerController extends Controller
{
    public function index()
    {
        $favorites = auth()->user()->favorites()->with(['product.tenant', 'product.category'])->get();
        $followedTenants = auth()->user()->following()->get();

        return \Inertia\Inertia::render('Buyer/Profile', [
            'favorites' => $favorites,
            'followedTenants' => $followedTenants,
        ]);
    }
}
