<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;

class LocationController extends Controller
{
    public function setCity(Request $request)
    {
        $request->validate([
            'city_id' => 'required|exists:cities,id',
        ]);

        $cityId = $request->city_id;
        session(['active_city_id' => $cityId]);
        Cookie::queue('conecta_city_id', $cityId, 60 * 24 * 365 * 5);

        return back()->with('success', 'Ciudad actualizada');
    }

    public function clearCity()
    {
        session()->forget('active_city_id');
        Cookie::queue(Cookie::forget('conecta_city_id'));

        return back()->with('success', 'Filtro de ciudad eliminado');
    }

    public function getCities()
    {
        $cities = Cache::rememberForever('cities_list', function () {
            return City::with('municipality.department')
                ->orderBy('name')
                ->get();
        });

        return response()->json($cities);
    }
}
