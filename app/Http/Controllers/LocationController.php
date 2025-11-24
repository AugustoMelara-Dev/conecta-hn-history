<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocationController extends Controller
{
    /**
     * Set the active city for filtering.
     *
     * SECURITY DIRECTIVE 2: Sticky Persistence
     * Store in both session AND cookie (forever)
     */
    public function setCity(Request $request)
    {
        $request->validate([
            'city_id' => 'required|exists:cities,id',
        ]);

        $cityId = $request->city_id;

        // Store in session
        session(['active_city_id' => $cityId]);

        // Create forever cookie (5 years)
        Cookie::queue('conecta_city_id', $cityId, 60 * 24 * 365 * 5);

        return back()->with('success', 'Ciudad actualizada');
    }

    /**
     * Clear the active city filter
     */
    public function clearCity()
    {
        session()->forget('active_city_id');
        Cookie::queue(Cookie::forget('conecta_city_id'));

        return back()->with('success', 'Filtro de ciudad eliminado');
    }

    /**
     * Get all cities for the location picker
     */
    public function getCities()
    {
        $cities = \Illuminate\Support\Facades\Cache::rememberForever('cities_list', function () {
            return City::with('municipality.department')
                ->orderBy('name')
                ->get();
        });

        return response()->json($cities);
    }
}
