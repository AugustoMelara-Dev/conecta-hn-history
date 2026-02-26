<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Shared\Contracts\CityReader;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\Cookie\Factory as CookieFactory;

/**
 * LocationController - Hexagonal Compliant (DIP Remediation)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE:                                               ║
 * ║  - Zero App\Models imports (DIP compliant)                               ║
 * ║  - Zero Cache:: facade usage (cache logic in CityReader implementation)  ║
 * ║  - Uses CityReader contract for data access                              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class LocationController extends Controller
{
    public function __construct(
        private CityReader $cityReader,
        private CookieFactory $cookie
    ) {}

    public function setCity(Request $request): RedirectResponse
    {
        $request->validate([
            'city_id' => 'required|integer|min:1',
        ]);

        $cityId = (int) $request->input('city_id');
        session(['active_city_id' => $cityId]);
        $this->cookie->queue('conecta_city_id', (string) $cityId, 60 * 24 * 365 * 5);

        return back()->with('success', 'Ciudad actualizada');
    }

    public function clearCity(): RedirectResponse
    {
        session()->forget('active_city_id');
        $this->cookie->queue($this->cookie->forget('conecta_city_id'));

        return back()->with('success', 'Filtro de ciudad eliminado');
    }

    public function getCities(): JsonResponse
    {
        // DELEGATED: Query and cache logic in CityReader implementation
        $cities = $this->cityReader->getAllCities();

        return response()->json($cities);
    }
}
